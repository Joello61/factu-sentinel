#!/usr/bin/env bash
#
# Restaure une sauvegarde produite par docker/backup/backup.sh - PostgreSQL et stockage
# documentaire local ensemble, jamais l'un sans l'autre (docs/06-technical-architecture.md,
# section 32 : un document sans sa métadonnée, ou l'inverse, serait problématique).
#
# DESTRUCTIF : recrée la base de données cible (DROP puis CREATE) et remplace intégralement
# backend/var/storage/documents avant de restaurer le contenu de l'archive - jamais une
# fusion partielle. Confirmation explicite requise sauf --yes.
#
# Usage : BACKUP_GPG_PASSPHRASE=... docker/backup/restore.sh <archive.tar.gpg> [--yes]

set -euo pipefail

ARCHIVE_PATH="${1:?Usage: restore.sh <archive.tar.gpg> [--yes]}"
CONFIRM="${2:-}"

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
POSTGRES_USER="${POSTGRES_USER:-factusentinel}"
POSTGRES_DB="${POSTGRES_DB:-factusentinel}"

if [ -z "${BACKUP_GPG_PASSPHRASE:-}" ]; then
  echo "Erreur : BACKUP_GPG_PASSPHRASE doit être définie." >&2
  exit 1
fi

if [ ! -f "$ARCHIVE_PATH" ]; then
  echo "Erreur : archive introuvable : $ARCHIVE_PATH" >&2
  exit 1
fi

if [ "$CONFIRM" != "--yes" ]; then
  echo "Ceci va DÉTRUIRE la base de données '$POSTGRES_DB' et le contenu de backend/var/storage/documents avant restauration."
  read -r -p "Confirmer la restauration depuis $ARCHIVE_PATH ? [y/N] " reply
  case "$reply" in
    [yY]) ;;
    *) echo "Annulé."; exit 1 ;;
  esac
fi

cd "$ROOT_DIR"

WORKDIR="$(mktemp -d)"
trap 'rm -rf "$WORKDIR"' EXIT

echo "Déchiffrement..."
gpg --batch --yes --passphrase "$BACKUP_GPG_PASSPHRASE" --pinentry-mode loopback \
  --decrypt --output "$WORKDIR/backup.tar" "$ARCHIVE_PATH"

tar -xf "$WORKDIR/backup.tar" -C "$WORKDIR"

echo "Restauration de PostgreSQL (recréation de '$POSTGRES_DB')..."
docker compose exec -T postgres dropdb -U "$POSTGRES_USER" --if-exists "$POSTGRES_DB"
docker compose exec -T postgres createdb -U "$POSTGRES_USER" "$POSTGRES_DB"
docker compose exec -T postgres psql -U "$POSTGRES_USER" "$POSTGRES_DB" < "$WORKDIR/database.sql"

echo "Restauration du stockage documentaire..."
# Même raisonnement que backup.sh : passe par le conteneur "backend" plutôt que par un
# chemin hôte, qui n'est un bind-mount du dépôt qu'en développement (Phase 17,
# docs/12-roadmap.md) - en production, seul le conteneur voit le volume Docker nommé
# ("storage_documents"). "/app/var/storage/documents" est alors le POINT DE MONTAGE de ce
# volume, pas un simple répertoire - "rm -rf" dessus échoue avec "Resource busy" (Linux
# interdit de supprimer un point de montage actif), constaté lors du premier test de
# restauration réel en production. On vide son CONTENU, jamais le répertoire lui-même.
docker compose exec -T backend sh -c 'find /app/var/storage/documents -mindepth 1 -delete'
docker compose exec -T backend tar -xzf - -C /app/var/storage < "$WORKDIR/storage.tar.gz"

echo "Restauration terminée depuis $ARCHIVE_PATH"
