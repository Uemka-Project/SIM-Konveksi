# syntax=docker/dockerfile:1.7

# Stage 1: install PHP dependencies tanpa source code.
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --prefer-dist \
    --no-scripts \
    --no-autoloader

# Stage 2: runtime (FrankenPHP = Caddy + PHP), listen di :8000.
FROM dunglas/frankenphp:1-php8.4.27-bookworm AS runtime

ARG APP_VERSION=0.0.0
ARG GIT_SHA=unknown

LABEL org.opencontainers.image.title="konveksi-backend" \
      org.opencontainers.image.version="${APP_VERSION}" \
      org.opencontainers.image.revision="${GIT_SHA}"

ENV APP_VERSION=${APP_VERSION} \
    GIT_SHA=${GIT_SHA} \
    SERVER_NAME=":8000" \
    APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr

# Ekstensi yang dibutuhkan Laravel + driver SQL Server (pdo_sqlsrv).
# install-php-extensions juga memasang ODBC Driver 18 for SQL Server.
RUN install-php-extensions \
        pdo_sqlsrv \
        sqlsrv \
        bcmath \
        intl \
        zip \
        gd \
        opcache \
        pcntl \
        simplexml

WORKDIR /app

COPY --from=vendor /app/vendor ./vendor
COPY . .

# Image runtime tidak punya composer; salin binary-nya untuk finalisasi
# autoload dengan source code lengkap (post-autoload-dump menjalankan
# `artisan package:discover` memakai PHP + ekstensi runtime).
COPY --from=vendor /usr/bin/composer /usr/bin/composer

RUN composer dump-autoload --optimize --classmap-authoritative --no-dev \
    && mkdir -p \
        storage/framework/cache \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 8000

CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]
