#!/usr/bin/env bash
#
# Invoqué par .github/workflows/deploy.yml (jamais manuellement en usage normal) - tire les
# images déjà construites et poussées vers GHCR par le job "build-and-push", jamais une
# reconstruction sur le serveur cible (plan Phase 17, docs/12-roadmap.md, section 5 :
# "l'image testée doit être exactement l'image déployée").
#
# Usage : ssh-deploy.sh <staging|production>
# Variables d'environnement attendues (fournies par deploy.yml via des secrets GitHub
# Environment, jamais en dur) :
#   SSH_PRIVATE_KEY, SSH_HOST, SSH_USER, DEPLOY_PATH, IMAGE_BASE, IMAGE_TAG

set -euo pipefail

ENVIRONMENT="${1:?Usage: ssh-deploy.sh <staging|production>}"

: "${SSH_PRIVATE_KEY:?}"
: "${SSH_HOST:?}"
: "${SSH_USER:?}"
: "${DEPLOY_PATH:?}"
: "${IMAGE_BASE:?}"
: "${IMAGE_TAG:?}"

SSH_KEY_FILE="$(mktemp)"
KNOWN_HOSTS_FILE="$(mktemp)"
trap 'rm -f "$SSH_KEY_FILE" "$KNOWN_HOSTS_FILE"' EXIT

printf '%s\n' "$SSH_PRIVATE_KEY" > "$SSH_KEY_FILE"
chmod 600 "$SSH_KEY_FILE"

# ssh-keyscan à chaque exécution plutôt qu'une clé d'hôte figée en secret : accepte la clé
# présentée par le serveur au moment de la connexion (confiance à la première utilisation,
# par exécution de ce job) - une empreinte fixée à l'avance serait plus stricte mais exige
# un secret supplémentaire à maintenir en cas de changement de serveur ; compromis
# documenté ici, à durcir explicitement si le contexte de menace l'exige un jour.
ssh-keyscan -H "$SSH_HOST" > "$KNOWN_HOSTS_FILE" 2>/dev/null

ENV_FILE=".env.${ENVIRONMENT}"
BACKEND_IMAGE="${IMAGE_BASE}-backend:${IMAGE_TAG}"
FRONTEND_IMAGE="${IMAGE_BASE}-frontend:${IMAGE_TAG}"
MUSTANG_IMAGE="${IMAGE_BASE}-mustang:${IMAGE_TAG}"

echo "Déploiement de ${ENVIRONMENT} - images taguées ${IMAGE_TAG}"

# shellcheck disable=SC2087
ssh -i "$SSH_KEY_FILE" -o UserKnownHostsFile="$KNOWN_HOSTS_FILE" "$SSH_USER@$SSH_HOST" bash -s <<EOF
set -euo pipefail
cd "$DEPLOY_PATH"

export BACKEND_IMAGE="$BACKEND_IMAGE"
export FRONTEND_IMAGE="$FRONTEND_IMAGE"
export MUSTANG_IMAGE="$MUSTANG_IMAGE"

echo "=== Récupération des images ==="
docker compose --env-file "$ENV_FILE" -f docker-compose.yml -f docker-compose.prod.yml pull

echo "=== Démarrage des nouveaux conteneurs ==="
docker compose --env-file "$ENV_FILE" -f docker-compose.yml -f docker-compose.prod.yml up -d

echo "=== Migrations de base de données ==="
# Étape distincte et explicite, après le démarrage des nouveaux conteneurs (plan
# Phase 17) - jamais implicite dans un entrypoint. Chaque migration ajoutée au dépôt doit
# rester purement additive (jamais de suppression/renommage de colonne en une seule
# migration) précisément pour que cet ordre reste sûr - voir le tableau de décision du
# plan Phase 17 sur le rollback.
docker compose --env-file "$ENV_FILE" -f docker-compose.yml -f docker-compose.prod.yml exec -T backend php bin/console doctrine:migrations:migrate --no-interaction

echo "Déploiement terminé (${ENVIRONMENT}, images ${IMAGE_TAG})."
EOF
