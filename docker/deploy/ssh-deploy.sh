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
#   SSH_PRIVATE_KEY, SSH_HOST, SSH_USER, DEPLOY_PATH, IMAGE_BASE, IMAGE_TAG,
#   INFISICAL_CLIENT_ID, INFISICAL_CLIENT_SECRET
#
# Secrets applicatifs (POSTGRES_*, APP_SECRET, JWT_PASSPHRASE, etc.) : plus aucun fichier
# ".env.production"/".env.staging" sur le serveur depuis la Phase 19 (Workstream B,
# docs/20-observability-infrastructure-migration.md) - injectés directement dans
# l'environnement du processus "docker compose" via "infisical run", jamais écrits sur
# disque. INFISICAL_CLIENT_ID/SECRET sont ceux d'une identité machine Universal Auth
# dédiée à CET environnement (jamais partagée entre staging et production, même principe
# que les autres secrets de ce projet) - accès en lecture seule, limité à l'environnement
# Infisical correspondant (voir github.com/Joello61/infrastructure pour la configuration
# des identités).

set -euo pipefail

ENVIRONMENT="${1:?Usage: ssh-deploy.sh <staging|production>}"

: "${SSH_PRIVATE_KEY:?}"
: "${SSH_HOST:?}"
: "${SSH_USER:?}"
: "${DEPLOY_PATH:?}"
: "${IMAGE_BASE:?}"
: "${IMAGE_TAG:?}"
: "${INFISICAL_CLIENT_ID:?}"
: "${INFISICAL_CLIENT_SECRET:?}"

# Projet Infisical "FactuSentinel" - identifiant non sensible (un ID de projet n'est pas un
# secret), constant quel que soit l'environnement ; seul l'environnement Infisical
# interrogé ("staging"/"production", voir plus bas) et l'identité machine utilisée
# changent réellement entre les deux déploiements.
INFISICAL_PROJECT_ID='3f7529af-9a52-4bc1-b442-e0390561084d'
# Infisical tourne sur le même VPS que la cible de déploiement, jamais exposé
# publiquement (tunnel SSH pour un accès humain, voir github.com/Joello61/infrastructure) -
# ce déploiement, lui, s'exécute DEPUIS le serveur cible lui-même (SSH), donc l'atteint
# directement en local, jamais besoin du tunnel.
INFISICAL_API_URL='http://localhost:8081'

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

# Synchronise le dépôt (docker-compose.prod.yml, docker/nginx/*.template, etc.) sur le
# serveur avec l'EXACT commit déjà testé et dont les images ci-dessus ont été construites
# (\$IMAGE_TAG identifie ce commit sans ambiguïté) - jamais un "git pull" qui suivrait la
# branche et pourrait diverger de ce qui a réellement été testé, même principe que "l'image
# testée doit être exactement l'image déployée" (plan Phase 17, section 5) étendu au code de
# configuration. HEAD detached délibérément : ce répertoire n'est jamais un espace de
# développement, seulement une cible de déploiement. Ne touche jamais aux fichiers
# non-suivis par Git (docker-compose.prod.traefik.yml) - constaté au premier déploiement
# réel : un changement de docker-compose.prod.yml (port du service "monitoring" rendu
# paramétrable) n'atteignait jamais le serveur sans cette étape, "pull" ne mettant à jour
# que les images, jamais le code.
echo "=== Synchronisation du code au commit testé (${IMAGE_TAG}) ==="
# GitHub n'autorise pas de récupérer un SHA arbitraire en tant que référence distante
# directe ("git fetch origin <sha>") - constaté en usage réel :
# "fatal: couldn't find remote ref <sha>". Ce déploiement n'est déclenché que depuis un
# commit déjà présent sur "main" (.github/workflows/deploy.yml, workflow_run sur le
# succès de la CI sur main) : récupérer la branche suffit à rendre ce commit disponible
# localement, avant de s'y positionner précisément.
git fetch origin main
git checkout --quiet "$IMAGE_TAG"

# "docker-compose.prod.observability.yml" ajoute nginx/backend/worker à
# "observability-shared" (socle partagé, Phase 19) - chargé UNIQUEMENT en production,
# jamais en staging (portée déjà documentée, voir ce fichier pour le raisonnement complet :
# la fusion de listes Compose est strictement additive, aucun override ne peut retirer un
# réseau ajouté dans un fichier de base - seul le fait de ne jamais charger ce fichier pour
# staging garantit l'exclusion, jamais un contenu conditionnel à l'intérieur du fichier).
COMPOSE_FILES="-f docker-compose.yml -f docker-compose.prod.yml -f docker-compose.prod.traefik.yml"
if [ "$ENVIRONMENT" = "production" ]; then
  COMPOSE_FILES="\$COMPOSE_FILES -f docker-compose.prod.observability.yml"
fi

# Authentification Infisical (Universal Auth, Phase 19 Workstream B) - identité machine
# dédiée à cet environnement, jamais de secret applicatif écrit sur disque depuis cette
# phase (docs/20-observability-infrastructure-migration.md). "INFISICAL_TOKEN" est
# automatiquement repris par "infisical run" ci-dessous, jamais besoin de "--token" explicite
# (vérifié sur la documentation officielle du CLI).
export INFISICAL_API_URL="$INFISICAL_API_URL"
export INFISICAL_TOKEN="\$(infisical login --method=universal-auth --client-id="$INFISICAL_CLIENT_ID" --client-secret="$INFISICAL_CLIENT_SECRET" --silent --plain)"

echo "=== Récupération des images ==="
docker compose \$COMPOSE_FILES pull

echo "=== Démarrage des nouveaux conteneurs ==="
# "--remove-orphans" : "docker compose up -d" ne supprime jamais de lui-même les conteneurs
# d'un service retiré d'un fichier Compose (constaté en pratique, Phase 19,
# docs/20-observability-infrastructure-migration.md - le retrait de la stack
# d'observabilité/monitoring embarquée aurait sinon laissé tourner des conteneurs orphelins
# indéfiniment, en conflit de port avec le nouveau socle partagé). Scope automatiquement
# limité au projet Compose courant (COMPOSE_PROJECT_NAME) - ne touche jamais aux
# conteneurs d'un autre projet (ex. /opt/infrastructure/). "infisical run" injecte les
# secrets applicatifs directement dans l'environnement de ce processus "docker compose" -
# jamais de fichier ".env.${ENVIRONMENT}" sur le disque du serveur.
infisical run --projectId="$INFISICAL_PROJECT_ID" --env="$ENVIRONMENT" -- docker compose \$COMPOSE_FILES up -d --remove-orphans

echo "=== Migrations de base de données ==="
# Étape distincte et explicite, après le démarrage des nouveaux conteneurs (plan
# Phase 17) - jamais implicite dans un entrypoint. Chaque migration ajoutée au dépôt doit
# rester purement additive (jamais de suppression/renommage de colonne en une seule
# migration) précisément pour que cet ordre reste sûr - voir le tableau de décision du
# plan Phase 17 sur le rollback. Jamais besoin de "infisical run" ici : "exec" s'exécute
# dans le conteneur "backend" déjà démarré, déjà porteur de son environnement depuis
# l'"up -d" précédent.
docker compose -f docker-compose.yml -f docker-compose.prod.yml exec -T backend php bin/console doctrine:migrations:migrate --no-interaction

echo "Déploiement terminé (${ENVIRONMENT}, images ${IMAGE_TAG})."
EOF
