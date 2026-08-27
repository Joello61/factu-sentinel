# syntax=docker/dockerfile:1
#
# Image Docker du backend Symfony (PHP 8.4).
#
# Choix retenu : PHP-FPM + Nginx en reverse proxy (docker/nginx/default.conf), cohérent
# avec la stack déjà actée dans docs/06-technical-architecture.md (ADR-007 : Nginx comme
# reverse proxy). FrankenPHP (app server PHP moderne avec support natif Symfony) a été
# envisagé mais volontairement écarté ici : introduire un nouveau runtime applicatif serait
# un changement de stack qui doit être explicitement décidé et documenté (nouvel ADR),
# pas introduit silencieusement à l'occasion de la création des fichiers Docker.
#
# Trois cibles ("--target") :
#   dev  - utilisée par docker-compose.yml, code source monté en volume
#   build - installe les dépendances de production, sert de base à "prod"
#   prod - image finale, sans outils de build, utilisateur non-root

FROM php:8.4-fpm-alpine AS base

# ext-ctype et ext-iconv sont fournies nativement par l'image officielle (composer.json
# les requiert explicitement). intl/pdo_pgsql/pgsql/zip sont nécessaires pour Doctrine,
# la locale française et le traitement des documents (07-data-model.md, 06-technical-architecture.md).
# ext-redis (PECL, pas un module core PHP) est nécessaire à symfony/redis-messenger
# (Phase 7, traitement asynchrone, docs/06-technical-architecture.md section 13) - version
# figée explicitement (6.1.0) plutôt que la dernière stable (6.3.0), dont l'installation
# PECL est cassée au moment de l'écriture (métadonnées REST XML manquantes sur pecl.php.net,
# vérifié lors de l'implémentation de la Phase 7 - à réévaluer si ce correctif est publié).
RUN apk add --no-cache \
        icu-dev \
        icu-data-full \
        postgresql-dev \
        libzip-dev \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && docker-php-ext-install -j"$(nproc)" \
        intl \
        pdo_pgsql \
        pgsql \
        zip \
        opcache \
    && pecl install redis-6.1.0 \
    && docker-php-ext-enable redis \
    && apk del .build-deps

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Limites d'upload (Phase 7, docs/12-roadmap.md) : voir docker/php/uploads.ini pour le
# détail - les valeurs par défaut de cette image (2M/8M) sont très en dessous de la limite
# produit de 20 Mo par fichier.
COPY docker/php/uploads.ini /usr/local/etc/php/conf.d/uploads.ini

WORKDIR /app

# ---------------------------------------------------------------------------
FROM base AS dev

# APP_ENV volontairement absent ici (contrairement aux stages build/prod ci-dessous, où
# elle reste une vraie protection de sécurité) : backend/.env commite déjà APP_ENV=dev par
# défaut, et ce fichier est systématiquement présent en développement (committé + monté en
# volume par docker-compose.yml). La définir ici en ferait en revanche une variable
# d'environnement réelle du conteneur, qui prime alors sur le
# `<server name="APP_ENV" value="test" force="true">` de backend/phpunit.dist.xml (vérifié :
# Symfony Dotenv redonne la priorité à un getenv() réel lors du bootEnv() de
# backend/tests/bootstrap.php) - `docker compose exec backend php bin/phpunit` démarrait
# alors silencieusement en environnement "dev" au lieu de "test" (service
# "test.service_container" introuvable). Ne pas la réintroduire ici sans revoir ce commentaire.
ENV COMPOSER_ALLOW_SUPERUSER=1

COPY composer.json composer.lock* symfony.lock* ./
RUN composer install --no-scripts --no-interaction --prefer-dist

# Le code source réel est monté en volume par docker-compose.yml en développement ;
# cette copie garantit néanmoins que l'image reste utilisable seule (ex. CI).
COPY . .

COPY docker/entrypoint-dev.sh /usr/local/bin/docker-entrypoint-dev.sh
COPY docker/entrypoint-worker-dev.sh /usr/local/bin/docker-entrypoint-worker-dev.sh
RUN chmod +x /usr/local/bin/docker-entrypoint-dev.sh /usr/local/bin/docker-entrypoint-worker-dev.sh

ENTRYPOINT ["docker-entrypoint-dev.sh"]
EXPOSE 9000
CMD ["php-fpm"]

# ---------------------------------------------------------------------------
FROM base AS build

ENV APP_ENV=prod \
    COMPOSER_ALLOW_SUPERUSER=1

COPY composer.json composer.lock* symfony.lock* ./
RUN composer install --no-dev --no-scripts --no-interaction --prefer-dist --optimize-autoloader

COPY . .

RUN composer dump-autoload --optimize --no-dev --classmap-authoritative

# ---------------------------------------------------------------------------
FROM base AS prod

ENV APP_ENV=prod

RUN addgroup -g 1000 app \
    && adduser -D -G app -u 1000 app

COPY --from=build --chown=app:app /app /app

# "COPY --chown" ne s'applique qu'au contenu copié, jamais au répertoire de destination
# pré-existant lui-même ("/app", créé root:root par "WORKDIR /app" dans le stage "base",
# en amont de la création de l'utilisateur "app" ci-dessus) - constaté à l'implémentation :
# sans ce répertoire "var/" créé et possédé explicitement par "app" avant le "USER app"
# ci-dessous, Symfony ne peut créer aucun sous-répertoire ("var/cache/prod", "var/log")
# au runtime ("Unable to create the cache directory"), le répertoire parent restant
# root:root et non accessible en écriture à un utilisateur non-root.
#
# "var/storage/documents" ci-dessous pour la même raison, mais avec une conséquence plus
# grave, constatée seulement au premier test réel de restauration en production : ce
# chemin est le POINT DE MONTAGE du volume Docker nommé "storage_documents"
# (docker-compose.prod.yml) - Docker recopie la propriété/le contenu d'un répertoire déjà
# présent dans l'image vers un volume nommé VIDE lors de son tout premier montage (jamais
# ensuite). Sans ce répertoire créé et possédé par "app" ici, un volume fraîchement créé
# héritait de la propriété root par défaut, rendant tout upload de document impossible
# ("Permission denied") - jamais rencontré avant cette vérification, aucun document réel
# n'ayant encore été uploadé en production.
RUN mkdir -p var/cache var/log var/storage/documents \
    && chown -R app:app var

# config/jwt/ est le point de montage du volume nommé "jwt_keys" (docker-compose.prod.yml,
# même raison que "var/storage/documents" ci-dessus) - créé et possédé par "app" avant le
# "USER app" ci-dessous pour la même raison : un volume nommé fraîchement créé hérite sinon
# de la propriété root, empêchant "app" d'y écrire les clés générées au démarrage
# (docker/entrypoint-prod.sh).
RUN mkdir -p config/jwt \
    && chown -R app:app config/jwt

COPY docker/entrypoint-prod.sh /usr/local/bin/docker-entrypoint-prod.sh
RUN chmod +x /usr/local/bin/docker-entrypoint-prod.sh

USER app

ENTRYPOINT ["docker-entrypoint-prod.sh"]

EXPOSE 9000

# Liveness faible mais sans dépendance supplémentaire (pas de cgi-fcgi/curl dans cette
# image) : prouve seulement que php-fpm accepte des connexions TCP sur son port FastCGI,
# pas qu'il répond correctement à une vraie requête. Le signal de santé de bout en bout
# (Nginx -> ce conteneur -> PostgreSQL) reste porté par le HEALTHCHECK du service "nginx"
# sur /api/health (backend/src/Shared/Controller/HealthController.php), pas celui-ci.
HEALTHCHECK --interval=30s --timeout=3s --start-period=10s --retries=3 \
    CMD php -r '$fp=@fsockopen("127.0.0.1",9000,$errno,$errstr,2);if(!$fp){exit(1);}fclose($fp);exit(0);'

CMD ["php-fpm"]
