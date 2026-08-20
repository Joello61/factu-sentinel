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
    && apk del .build-deps

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

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
RUN chmod +x /usr/local/bin/docker-entrypoint-dev.sh

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

USER app

EXPOSE 9000
CMD ["php-fpm"]
