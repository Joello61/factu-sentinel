#!/usr/bin/env bash
set -euo pipefail

# Émission initiale d'un certificat Let's Encrypt pour un domaine qui n'en a encore aucun -
# à exécuter une seule fois par domaine, avant le tout premier déploiement réel (Bloc B,
# docs/12-roadmap.md Phase 17). Jamais nécessaire ensuite : le renouvellement périodique
# passe par renew-cert.sh.
#
# Pourquoi ce script existe (problème d'amorçage documenté dans docker/nginx/README.md) :
# Nginx refuse de démarrer si les fichiers référencés par "ssl_certificate"/
# "ssl_certificate_key" (docker/nginx/prod.conf.template) n'existent pas - résolus au
# chargement de la configuration, pas à la requête. Ce script crée d'abord un certificat
# auto-signé temporaire au même chemin pour permettre à Nginx de démarrer et de servir le
# défi ACME HTTP-01, obtient le vrai certificat pendant que Nginx tourne déjà, puis
# recharge Nginx avec le certificat réel.
#
# Usage : docker/nginx/bootstrap-cert.sh <domaine> <email>

DOMAIN="${1:?Usage: bootstrap-cert.sh <domaine> <email>}"
EMAIL="${2:?Usage: bootstrap-cert.sh <domaine> <email>}"
COMPOSE=(docker compose -f docker-compose.yml -f docker-compose.prod.yml)

echo "1/4 - Certificat auto-signé temporaire pour permettre à Nginx de démarrer..."
"${COMPOSE[@]}" run --rm --entrypoint sh certbot -c "
  mkdir -p /etc/letsencrypt/live/$DOMAIN &&
  openssl req -x509 -nodes -newkey rsa:2048 -days 1 \
    -keyout /etc/letsencrypt/live/$DOMAIN/privkey.pem \
    -out /etc/letsencrypt/live/$DOMAIN/fullchain.pem \
    -subj '/CN=localhost'
"

echo "2/4 - Démarrage de Nginx avec le certificat temporaire..."
PUBLIC_DOMAIN="$DOMAIN" "${COMPOSE[@]}" up -d nginx

echo "3/4 - Suppression du certificat temporaire et émission du certificat réel (défi HTTP-01)..."
"${COMPOSE[@]}" run --rm --entrypoint sh certbot -c "rm -rf /etc/letsencrypt/live/$DOMAIN /etc/letsencrypt/archive/$DOMAIN /etc/letsencrypt/renewal/$DOMAIN.conf"
"${COMPOSE[@]}" run --rm certbot certonly --webroot -w /var/www/certbot -d "$DOMAIN" --email "$EMAIL" --agree-tos --non-interactive

echo "4/4 - Rechargement de Nginx avec le certificat réel..."
PUBLIC_DOMAIN="$DOMAIN" "${COMPOSE[@]}" exec nginx nginx -s reload

echo "Terminé. Vérifier : https://$DOMAIN/api/health"
