#!/usr/bin/env bash
#
# Automatisation périodique de docker/backup/backup.sh (Phase 17, docs/12-roadmap.md) -
# aucune automatisation n'existait avant cette phase (backup.sh restait un mécanisme
# manuel, Phase 10). À exécuter via cron/systemd timer côté hôte, au moins une fois par
# jour pour tenir le RPO/RTO 24h/24h déjà acté (docs/10-security-privacy.md section 59) -
# jamais un conteneur/démon supplémentaire dans la stack Docker elle-même.
#
# Ce que ce script ajoute par rapport à backup.sh seul :
#   1. Cible la stack de production/staging (COMPOSE_FILE), jamais la stack de dev par
#      défaut.
#   2. Envoie l'archive chiffrée vers un stockage objet distant, hors du serveur
#      applicatif (rclone, compatible avec l'API S3 d'OVHcloud Object Storage comme avec
#      tout autre stockage compatible S3 - jamais un outil propriétaire à un hébergeur
#      précis, cohérent avec le caractère générique du reste de la Phase 17).
#   3. Applique une rétention (locale ET distante) plutôt que de laisser les archives
#      s'accumuler indéfiniment.
#
# Prérequis (une seule fois, documentés dans docker/backup/README.md) :
#   - rclone installé et configuré sur l'hôte (`rclone config`), un remote nommé existant.
#   - BACKUP_GPG_PASSPHRASE disponible dans l'environnement d'exécution (gestionnaire de
#     secrets de l'hébergeur ou fichier chargé par le service cron/systemd - jamais en
#     dur dans ce script ni dans une crontab committée).
#
# Variables attendues :
#   BACKUP_GPG_PASSPHRASE   (obligatoire, jamais stockée ici)
#   BACKUP_RCLONE_REMOTE    (obligatoire, ex. "ovh-backup:factusentinel-backups")
#   BACKUP_RETENTION_DAYS   (optionnel, défaut 14)
#   COMPOSE_ENV_FILE        (optionnel, ex. ".env.production" - passé à --env-file)
#   BACKUP_MONITORING_PUSH_URL (optionnel) - URL d'un moniteur "push" Uptime Kuma
#     (docker/monitoring/README.md) : appelée en cas de succès uniquement, pour qu'une
#     absence de sauvegarde silencieuse (cron qui ne se déclenche plus, script qui échoue
#     avant même de commencer) déclenche une alerte au bout du délai attendu configuré
#     dans Uptime Kuma - jamais l'inverse (ce script ne signale jamais explicitement
#     l'échec, l'absence de signal EST le signal).
#
# Usage : docker/backup/automated-backup.sh

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

if [ -z "${BACKUP_GPG_PASSPHRASE:-}" ]; then
  echo "Erreur : BACKUP_GPG_PASSPHRASE doit être définie." >&2
  exit 1
fi

if [ -z "${BACKUP_RCLONE_REMOTE:-}" ]; then
  echo "Erreur : BACKUP_RCLONE_REMOTE doit être définie (ex. \"ovh-backup:factusentinel-backups\")." >&2
  exit 1
fi

RETENTION_DAYS="${BACKUP_RETENTION_DAYS:-14}"
BACKUP_DIR="$ROOT_DIR/backups"

# COMPOSE_FILE (variable reconnue nativement par "docker compose", séparateur ":" sur
# Linux/macOS - vérifié sur la documentation officielle Docker le 26/08/2026) plutôt que
# de modifier backup.sh/restore.sh pour leur faire connaître l'overlay de production :
# ils restent ainsi utilisables tels quels en développement comme en production.
if [ -n "${COMPOSE_ENV_FILE:-}" ]; then
  export COMPOSE_FILE="docker-compose.yml:docker-compose.prod.yml"
  export COMPOSE_ENV_FILES="$COMPOSE_ENV_FILE"

  # COMPOSE_ENV_FILES ci-dessus ne fait que dire à "docker compose" quel fichier lire pour
  # SES PROPRES commandes (exec, up, ...) - il n'exporte rien dans CE process shell.
  # backup.sh lit pourtant $POSTGRES_USER/$POSTGRES_DB directement (pas via Docker Compose)
  # pour sa commande "pg_dump -U ...", avec un repli sur les valeurs de dev
  # ("factusentinel") si absentes - jamais rencontré avant le premier essai réel en
  # production, où le rôle "factusentinel" n'existe pas (POSTGRES_USER réel :
  # "factusentinel_prod"). Charger explicitement le fichier dans ce process pour que ces
  # variables soient également visibles ici, POSTGRES_PASSWORD y compris (déjà lu par
  # "docker compose exec" de toute façon via COMPOSE_ENV_FILES - aucune frontière de
  # confiance supplémentaire franchie en l'exportant aussi dans ce process).
  set -a
  # shellcheck disable=SC1090
  source "$COMPOSE_ENV_FILE"
  set +a
fi

echo "=== Sauvegarde ==="
BACKUP_OUTPUT="$("$ROOT_DIR/docker/backup/backup.sh" "$BACKUP_DIR" | tee /dev/stderr)"
LATEST_BACKUP="$(echo "$BACKUP_OUTPUT" | sed -n 's/^Sauvegarde écrite : //p')"

if [ -z "$LATEST_BACKUP" ] || [ ! -f "$LATEST_BACKUP" ]; then
  echo "Erreur : impossible de déterminer le chemin de l'archive produite par backup.sh." >&2
  exit 1
fi

echo "=== Envoi vers le stockage distant ($BACKUP_RCLONE_REMOTE) ==="
rclone copy "$LATEST_BACKUP" "$BACKUP_RCLONE_REMOTE" --checksum

echo "=== Rétention locale (> ${RETENTION_DAYS}j) ==="
find "$BACKUP_DIR" -maxdepth 1 -name 'factusentinel-backup-*.tar.gpg' -mtime "+${RETENTION_DAYS}" -print -delete

echo "=== Rétention distante (> ${RETENTION_DAYS}j) ==="
rclone delete "$BACKUP_RCLONE_REMOTE" --min-age "${RETENTION_DAYS}d"

if [ -n "${BACKUP_MONITORING_PUSH_URL:-}" ]; then
  echo "=== Signal de succès (monitoring push) ==="
  curl -fsS -m 10 "$BACKUP_MONITORING_PUSH_URL" > /dev/null || echo "Avertissement : signal de succès non envoyé (sauvegarde elle-même réussie)." >&2
fi

echo "Terminé : $LATEST_BACKUP"
