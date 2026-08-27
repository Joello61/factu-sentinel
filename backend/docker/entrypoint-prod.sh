#!/bin/sh
set -e

# config/jwt/*.pem est gitignoré (clé privée, jamais versionnée) et l'image de production
# n'exécute jamais "composer install" avec les scripts du bundle ("--no-scripts",
# backend/Dockerfile, stage "build") - sans cette génération, aucune paire de clés
# n'existe jamais dans un conteneur de production, et toute route passant par le firewall
# JWT échoue silencieusement (login, refresh, tout endpoint authentifié). Incident réel
# constaté en production (Phase 18, 27/08/2026) : "POST /auth/login" échouait en 500,
# "JWTEncodeFailureException" -> "InvalidKeyProvided: It was not possible to parse your
# key" - le fichier n'avait jamais existé sur aucun environnement réel depuis le premier
# déploiement (Phase 17).
#
# Écrit sur le volume nommé "jwt_keys" (docker-compose.prod.yml), qui persiste au-delà
# d'un redéploiement (contrairement au système de fichiers de l'image, recréé à chaque
# nouvelle image) - "--skip-if-exists" ne régénère donc jamais une paire déjà générée par
# un déploiement précédent, même principe que docker/entrypoint-dev.sh.
php bin/console lexik:jwt:generate-keypair --skip-if-exists

# Second trousseau, structurellement séparé (Phase 15, ADR-009,
# backend/config/services.yaml, section "platform_admin_jwt.*") - même raison ci-dessus,
# même volume "jwt_keys".
php bin/console app:platform-admin:jwt:generate-keypair --skip-if-exists

exec "$@"
