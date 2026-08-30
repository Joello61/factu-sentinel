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
#   - CLI Infisical installé sur l'hôte (Phase 19 Workstream B,
#     docs/20-observability-infrastructure-migration.md).
#
# Variables attendues :
#   BACKUP_GPG_PASSPHRASE   (obligatoire, jamais stockée ici)
#   BACKUP_RCLONE_REMOTE    (obligatoire, ex. "ovh-backup:factusentinel-backups")
#   BACKUP_RETENTION_DAYS   (optionnel, défaut 14)
#   INFISICAL_CLIENT_ID/INFISICAL_CLIENT_SECRET (optionnel - identité machine Universal
#     Auth, même identité que le déploiement de production réutilisée ici, portée de
#     lecture identique : POSTGRES_USER/POSTGRES_DB, jamais un secret que cette identité
#     ne pouvait déjà lire) - sans ces deux variables, POSTGRES_USER/POSTGRES_DB
#     retombent sur les défauts de dev (comportement identique à avant cette phase).
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
BACKUP_RUNNER=()
if [ -n "${INFISICAL_CLIENT_ID:-}" ] && [ -n "${INFISICAL_CLIENT_SECRET:-}" ]; then
  export COMPOSE_FILE="docker-compose.yml:docker-compose.prod.yml"

  # "docker compose exec" valide la configuration fusionnée avant d'agir, même s'il ne
  # fait que se connecter à un conteneur déjà démarré (jamais besoin de créer quoi que ce
  # soit à partir de ces images ici) - sans BACKEND_IMAGE/FRONTEND_IMAGE/MUSTANG_IMAGE,
  # "image: '${BACKEND_IMAGE}'" s'interpole en chaîne vide et Compose refuse la commande
  # entière ("invalid compose project", constaté en pratique, 30/08/2026). ssh-deploy.sh
  # les exporte lui-même avant tout "docker compose" (SHA testé, connu à l'avance) - ce
  # script n'a pas cette information, il la déduit donc des conteneurs réellement en
  # cours d'exécution plutôt que de la supposer.
  COMPOSE_PROJECT_LABEL="$(basename "$ROOT_DIR")"
  for svc_var in BACKEND_IMAGE:backend FRONTEND_IMAGE:frontend MUSTANG_IMAGE:mustang; do
    var_name="${svc_var%%:*}"
    svc_name="${svc_var##*:}"
    container_id="$(docker ps -q \
      --filter "label=com.docker.compose.project=${COMPOSE_PROJECT_LABEL}" \
      --filter "label=com.docker.compose.service=${svc_name}")"
    if [ -z "$container_id" ]; then
      echo "Erreur : aucun conteneur en cours d'exécution pour le service \"${svc_name}\" (projet \"${COMPOSE_PROJECT_LABEL}\")." >&2
      exit 1
    fi
    export "${var_name}=$(docker inspect --format '{{.Config.Image}}' "$container_id")"
  done

  # Remplace le "source .env.production" d'avant la Phase 19 (Workstream B,
  # docs/20-observability-infrastructure-migration.md) - plus aucun fichier ".env.production"
  # requis sur le serveur. "backup.sh" lit $POSTGRES_USER/$POSTGRES_DB directement (pas via
  # Docker Compose) pour sa commande "pg_dump -U ..." - "infisical run" les injecte dans
  # l'environnement du processus qui exécute backup.sh, exactement le même mécanisme que
  # ssh-deploy.sh pour le déploiement lui-même.
  export INFISICAL_API_URL='http://localhost:8081'
  INFISICAL_TOKEN="$(infisical login --method=universal-auth --client-id="$INFISICAL_CLIENT_ID" --client-secret="$INFISICAL_CLIENT_SECRET" --silent --plain)"
  export INFISICAL_TOKEN
  BACKUP_RUNNER=(infisical run --projectId='3f7529af-9a52-4bc1-b442-e0390561084d' --env=production --)
fi

echo "=== Sauvegarde ==="
BACKUP_OUTPUT="$("${BACKUP_RUNNER[@]}" "$ROOT_DIR/docker/backup/backup.sh" "$BACKUP_DIR" | tee /dev/stderr)"
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
