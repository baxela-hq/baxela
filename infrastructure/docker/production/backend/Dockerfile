# syntax=docker/dockerfile:1
# Production backend image (php-fpm). Build context is the repo root:
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
RUN composer install \
    --no-dev \
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

RUN chmod +x /usr/local/bin/docker-app-entrypoint \
    && chown -R www-data:www-data /var/www/html

USER www-data

# Caches Laravel's package manifest + config/routes/views/events, then hands
# off to the standard php image entrypoint (CMD defaults to php-fpm).
ENTRYPOINT ["docker-app-entrypoint"]
CMD ["php-fpm"]
