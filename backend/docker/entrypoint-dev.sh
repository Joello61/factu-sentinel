#!/bin/sh
set -e

# Le volume nommé "backend_vendor" (docker-compose.yml) persiste au-delà d'un rebuild
# d'image : sans ce réinstall, vendor/ resterait figé sur l'état du build précédent dès
# qu'une dépendance change dans composer.json, avec une erreur "Class not found" au
# démarrage plutôt qu'une explication claire.
composer install --no-interaction --prefer-dist

exec "$@"
