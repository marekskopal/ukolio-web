FROM composer:2.9.7 AS composer
FROM mlocati/php-extension-installer:2.11.1 AS php-extension-installer
FROM dunglas/frankenphp:1.12.2-php8.5.6-alpine AS php

COPY --from=php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/

RUN install-php-extensions \
    curl \
    pdo_mysql \
    opcache \
    mbstring \
    pcov \
    sockets \
    zip \
    igbinary \
    memcached

RUN apk add --no-cache curl tzdata

COPY --from=composer /usr/bin/composer /usr/local/bin/composer

COPY ./bin /app/bin
COPY ./migrations /app/migrations
COPY ./src /app/src
COPY ./translations /app/translations
COPY ./Caddyfile /etc/frankenphp/Caddyfile
COPY ./docker/php-uploads.ini /usr/local/etc/php/conf.d/uploads.ini
COPY ./composer.json ./composer.lock* /app/

WORKDIR /app/

RUN rm -rf /app/vendor \
    && COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --no-progress --classmap-authoritative -d /app/ \
    && composer clear-cache
