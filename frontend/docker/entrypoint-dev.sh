#!/bin/sh
set -e

# Le volume nommé "frontend_node_modules" (docker-compose.yml) persiste au-delà d'un
# rebuild d'image : sans ce réinstall, node_modules resterait figé sur l'état du build
# précédent dès qu'une dépendance change dans package.json, avec un "Module not found"
# au démarrage plutôt qu'une explication claire.
npm install

exec "$@"
