# Image PHP-FPM pour l'API Laravel. Sert aussi au scheduler et au worker de
# file d'attente (mêmes dépendances, seule la commande lancée change — voir
# docker-compose.yml) : un seul Dockerfile pour les 3 services backend.
# PHP 8.4 : le composer.lock verrouille des paquets (symfony/*, laravel/framework
# v13.17) qui exigent >=8.4.1 malgré le "php": "^8.3" de composer.json.
FROM php:8.4-fpm-alpine AS base

# Bibliothèques système requises par les extensions PHP ci-dessous
# (GD pour intervention/image, zip pour les exports, intl pour les locales).
RUN apk add --no-cache \
        mariadb-client \
        libpng-dev \
        libjpeg-turbo-dev \
        libwebp-dev \
        freetype-dev \
        libzip-dev \
        icu-dev \
        $PHPIZE_DEPS \
    && docker-php-ext-configure gd --with-jpeg --with-webp --with-freetype \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        gd \
        zip \
        bcmath \
        intl \
        pcntl \
        exif \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del $PHPIZE_DEPS

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Le client mariadb (utilisé par `artisan migrate` pour charger le schéma
# figé, voir database/schema/mysql-schema.sql) exige TLS par défaut ; le
# serveur MySQL du réseau interne Docker le désactive (--skip-ssl). Sans ce
# fichier le chargement du schéma échoue au tout premier démarrage.
RUN mkdir -p /etc/my.cnf.d \
    && printf '[client]\nssl=0\n' > /etc/my.cnf.d/no-ssl.cnf

WORKDIR /var/www/html

# Dépendances installées avant le reste du code : une modification du code
# seul ne réinvalide pas cette couche. Contexte de build = racine du dépôt
# (voir docker-compose.yml) pour pouvoir aussi copier docker/backend/ ci-dessous.
COPY backend/composer.json backend/composer.lock ./
RUN composer install \
        --no-dev \
        --no-scripts \
        --no-autoloader \
        --prefer-dist

COPY backend/ .

RUN composer dump-autoload --optimize --no-dev \
    && chown -R www-data:www-data storage bootstrap/cache \
    && rm -f public/storage \
    && ln -s ../storage/app/public public/storage

COPY docker/backend/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

USER www-data

ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]

# ── Variante DEV, jamais utilisée en production ─────────────────────────────
# Sert uniquement à charger des données de démonstration dans une base Docker
# locale (vérification visuelle) : `DemoSeeder` dépend de `fakerphp/faker`
# (require-dev), absent de l'image `base` ci-dessus par choix — une image de
# production n'embarque pas d'outillage de développement. `docker-compose.yml`
# cible explicitement `base` pour les 3 services backend ; ce stage `dev`
# n'est construit qu'à la demande (`--target dev`), jamais par un `up` normal.
FROM base AS dev
USER root
RUN composer install --no-interaction --prefer-dist
USER www-data
