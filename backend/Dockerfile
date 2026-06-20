FROM composer:2.10.1 AS composer
FROM mlocati/php-extension-installer:2.11.12 AS php-extension-installer
# Thread-safe (ZTS) v8js build, matching FrankenPHP's embedded ZTS PHP. Provides
# ext-v8js for the low-code scripting sandbox (U-80); see php-v8js-docker. Pin to
# a digest in production for reproducible, tamper-evident builds.
FROM marekskopal/php-v8js:8.5-zts-alpine AS v8js
FROM dunglas/frankenphp:1.12.4-php8.5.7-alpine AS php

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

# curl/tzdata/supervisor for the runtime; nodejs-libs provides the libnode that
# v8js.so links against (V8 is embedded in Node, no separate libv8 in Alpine).
RUN apk add --no-cache curl tzdata supervisor nodejs-libs

# Copy the prebuilt ZTS v8js extension. It is loaded only by the script-worker
# process (via `php -d extension=v8js.so` in supervisord.conf), not by FrankenPHP
# or the main AMQP consumer, so the heavyweight V8 runtime stays out of the web
# tier. The extension-dir suffix is the Zend module API stamp; identical across
# all PHP 8.5.x, so the 8.5-zts image's .so loads into this 8.5.6 runtime.
COPY --from=v8js /usr/local/lib/php/extensions/no-debug-zts-20250925/v8js.so \
     /usr/local/lib/php/extensions/no-debug-zts-20250925/v8js.so

COPY --from=composer /usr/bin/composer /usr/local/bin/composer

COPY ./bin /app/bin
COPY ./migrations /app/migrations
COPY ./src /app/src
COPY ./translations /app/translations
COPY ./Caddyfile /etc/frankenphp/Caddyfile
COPY ./docker/php-uploads.ini /usr/local/etc/php/conf.d/uploads.ini
COPY ./docker/supervisord.conf /etc/supervisord.conf
COPY ./composer.json ./composer.lock* /app/

WORKDIR /app/

RUN rm -rf /app/vendor \
    && COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --no-progress --classmap-authoritative -d /app/ \
    && composer clear-cache

ENTRYPOINT ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]
