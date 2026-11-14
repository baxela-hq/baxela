# syntax=docker/dockerfile:1
# Production backend image (Laravel Octane + RoadRunner). Build context is
# the repo root:
#   docker build -f infrastructure/docker/production/backend/Dockerfile .
# Runtime env (APP_*, DB_*, ...) is injected at run time via env_file —
# nothing secret is baked into the image.

######## base: extension set shared with develop, build deps purged ########
# PHP 8.4 (not 8.3): composer.lock's Symfony components require >=8.4.1.
FROM php:8.4-fpm-bookworm AS base

# Runtime libraries the compiled extensions link against (kept in the image)
RUN apt-get update && apt-get install -y --no-install-recommends \
        ca-certificates \
        libpng16-16 \
        libjpeg62-turbo \
        libfreetype6 \
        libzip4 \
        libonig5 \
        libicu72 \
        libxml2 \
        libcurl4 \
        libssl3 \
        libpq5 \
    && rm -rf /var/lib/apt/lists/*

# Build dependencies are installed, used, and purged in a single layer
RUN set -eux; \
    buildDeps=' \
        build-essential \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        libzip-dev \
        libonig-dev \
        libxml2-dev \
        libcurl4-openssl-dev \
        libssl-dev \
        libicu-dev \
        libpq-dev \
    '; \
    apt-get update; \
    apt-get install -y --no-install-recommends $buildDeps; \
    docker-php-ext-configure gd --with-freetype --with-jpeg; \
    docker-php-ext-install -j"$(nproc)" \
        bcmath \
        curl \
        ftp \
        gd \
        mbstring \
        pdo \
        pdo_mysql \
        pdo_pgsql \
        zip \
        intl \
        xml \
        soap \
        exif \
        pcntl \
        opcache \
        sockets; \
    pecl install redis; \
    docker-php-ext-enable redis; \
    apt-get purge -y --auto-remove $buildDeps; \
    rm -rf /var/lib/apt/lists/*

######## deps: composer vendor (no dev, no scripts — entrypoint handles those) ########
FROM composer:2 AS deps
WORKDIR /app

# Modules/*/composer.json are merged by wikimedia/composer-merge-plugin,
# so the whole app (not just the root manifest) must be present.
COPY apps/backend ./
# --ignore-platform-reqs: this stage is a vendor staging area built on the
# plain composer image, which lacks ext-gd/ext-sockets etc. The runtime
# stage compiles the full extension set, so the resulting vendor tree is
# complete; the platform check here would only fail spuriously (packages
# like mpdf and roadrunner/worker declare those extensions).
RUN composer install \
    --no-dev \
    --ignore-platform-reqs \
    --optimize-autoloader \
    --no-scripts \
    --no-interaction \
    --prefer-dist

######## runtime: app + vendor, non-root ########
FROM base AS runtime
WORKDIR /var/www/html

COPY --from=deps /app .
COPY infrastructure/docker/production/backend/php.ini /usr/local/etc/php/conf.d/app.ini
COPY infrastructure/docker/production/backend/entrypoint.sh /usr/local/bin/docker-app-entrypoint
# RoadRunner binary, pinned to the exact version the app is tested against
# (spiral-packages in composer.lock + Octane's version checks). Kept on PATH
# outside the application tree; only this image's HTTP server uses it — the
# queue/scheduler/reverb containers keep their plain artisan commands.
COPY --from=ghcr.io/roadrunner-server/roadrunner:3.0.1 /usr/bin/rr /usr/local/bin/rr

RUN chmod +x /usr/local/bin/docker-app-entrypoint \
    && chown -R www-data:www-data /var/www/html

USER www-data

# Caches Laravel's package manifest + config/routes/views/events, then hands
# off to the standard php image entrypoint (CMD starts Octane/RoadRunner).
# The startup-command contract mirrors development: explicit worker sizing
# and an RPC listener bound to loopback only (never 0.0.0.0). queue,
# scheduler, reverb and migrate set their own `command:` in
# docker-compose.prod.yml and are unaffected by this default.
ENTRYPOINT ["docker-app-entrypoint"]
CMD ["php", "artisan", "octane:start", "--host=0.0.0.0", "--port=8000", "--rpc-host=127.0.0.1", "--workers=4", "--max-requests=500"]
