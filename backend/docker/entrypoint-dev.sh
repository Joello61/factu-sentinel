#!/bin/sh
set -e

# Le volume nommé "backend_vendor" (docker-compose.yml) persiste au-delà d'un rebuild
# d'image : sans ce réinstall, vendor/ resterait figé sur l'état du build précédent dès
# qu'une dépendance change dans composer.json, avec une erreur "Class not found" au
# démarrage plutôt qu'une explication claire.
composer install --no-interaction --prefer-dist

# config/jwt/*.pem est gitignoré (clé privée, jamais versionnée) : sans cette génération,
# un checkout neuf (nouveau contributeur, CI) n'a aucune paire de clés et toute route
# passant par le firewall JWT échoue silencieusement (login, refresh, tout endpoint
# authentifié) - --skip-if-exists la rend idempotente sans écraser une paire déjà générée.
php bin/console lexik:jwt:generate-keypair --skip-if-exists

# Phase 7 (docs/06-technical-architecture.md, section 30) a introduit le service "worker"
# (docker-compose.yml), qui partage la même image et le même bind-mount source que ce
# conteneur - docker/entrypoint-worker-dev.sh attend ce marqueur avant de démarrer, plutôt
# que de refaire composer install/cache:clear en parallèle sur le même var/cache/ (une
# première tentative avec flock a échoué : le verrouillage de fichier entre conteneurs n'est
# pas fiable sur un bind-mount Docker Desktop de ce type, constaté à l'implémentation).
mkdir -p /app/var
touch /app/var/.backend-ready

exec "$@"
