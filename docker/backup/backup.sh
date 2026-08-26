#!/usr/bin/env bash
#
# Sauvegarde PostgreSQL + stockage documentaire local, chiffrée (Phase 10 - Security
# Hardening, docs/12-roadmap.md ; docs/10-security-privacy.md, section 54 ; RPO/RTO 24h,
# section 59).
#
# Le stockage documentaire du MVP est local (backend/var/storage/documents, dette technique
# intentionnelle actée dans ADR-007) : sans cette sauvegarde dédiée, une perte du volume
# applicatif serait irréversible, contrairement à un stockage objet distant déjà répliqué.
#
# Chiffrement : gpg --symmetric (AES256). Choix vérifié à l'implémentation plutôt que
# supposé : gpg est déjà présent par défaut sur la quasi-totalité des systèmes Linux (aucune
# nouvelle dépendance à installer), contrairement à age qui exigerait un binaire séparé -
# critère décisif pour un développeur solo (docs/10-security-privacy.md, section 51,
# "approche proportionnée"). À réévaluer explicitement si l'automatisation Phase 13 change
# ce contexte (ex. gestion de clé via un service managé de l'hébergeur retenu).
#
# Gestion de la clé - point de sécurité, pas un détail : BACKUP_GPG_PASSPHRASE doit être
# fournie par l'appelant (gestionnaire de secrets, coffre-fort, saisie manuelle) - jamais
# écrite par ce script, jamais stockée dans le répertoire de sortie ni committée. Un backup
# chiffré dont la clé est à côté n'est pas une sauvegarde sécurisée.
#
# Usage : BACKUP_GPG_PASSPHRASE=... docker/backup/backup.sh [répertoire-de-sortie]
# Prérequis : la stack docker compose (docs/06-technical-architecture.md, section 30) est
# démarrée (service "postgres" accessible via "docker compose exec").

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
BACKUP_DIR="${1:-$ROOT_DIR/backups}"
POSTGRES_USER="${POSTGRES_USER:-factusentinel}"
POSTGRES_DB="${POSTGRES_DB:-factusentinel}"

if [ -z "${BACKUP_GPG_PASSPHRASE:-}" ]; then
  echo "Erreur : BACKUP_GPG_PASSPHRASE doit être définie (jamais stockée par ce script)." >&2
  exit 1
fi

cd "$ROOT_DIR"

WORKDIR="$(mktemp -d)"
trap 'rm -rf "$WORKDIR"' EXIT

TIMESTAMP="$(date -u +%Y%m%dT%H%M%SZ)"
mkdir -p "$BACKUP_DIR"

echo "Sauvegarde PostgreSQL (${POSTGRES_DB})..."
docker compose exec -T postgres pg_dump -U "$POSTGRES_USER" "$POSTGRES_DB" > "$WORKDIR/database.sql"

echo "Sauvegarde du stockage documentaire..."
# Passe par "docker compose exec" plutôt que de lire directement
# "$ROOT_DIR/backend/var/storage" sur l'hôte (Phase 17, docs/12-roadmap.md) : ce chemin
# n'est un bind-mount du dépôt qu'en développement (docker-compose.yml) - en production
# (docker-compose.prod.yml), STORAGE_LOCAL_PATH est porté par un volume Docker nommé
# ("storage_documents"), jamais accessible directement depuis le système de fichiers de
# l'hôte. Passer par le conteneur fonctionne identiquement dans les deux cas.
docker compose exec -T backend tar -czf - -C /app/var/storage documents > "$WORKDIR/storage.tar.gz"

echo "Assemblage de l'archive..."
tar -cf "$WORKDIR/backup.tar" -C "$WORKDIR" database.sql storage.tar.gz

OUTPUT="$BACKUP_DIR/factusentinel-backup-$TIMESTAMP.tar.gpg"
echo "Chiffrement (AES256)..."
gpg --batch --yes --passphrase "$BACKUP_GPG_PASSPHRASE" --pinentry-mode loopback \
  --symmetric --cipher-algo AES256 --output "$OUTPUT" "$WORKDIR/backup.tar"

echo "Sauvegarde écrite : $OUTPUT"
