#!/usr/bin/env bash
set -euo pipefail

# À exécuter périodiquement via cron/systemd timer côté hôte (ex. une fois par jour) -
# Certbot ne renouvelle réellement qu'à l'approche de l'expiration (30 jours avant, par
# défaut), cet appel est donc sûr à répéter sans effet la plupart du temps. Voir
# docker/nginx/README.md pour l'automatisation complète et pour bootstrap-cert.sh,
# nécessaire une seule fois avant la toute première exécution de ce script.

COMPOSE=(docker compose -f docker-compose.yml -f docker-compose.prod.yml)

"${COMPOSE[@]}" run --rm certbot renew --webroot -w /var/www/certbot --quiet
"${COMPOSE[@]}" exec nginx nginx -s reload
