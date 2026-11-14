# Development image. Serves the application via Laravel Octane + RoadRunner
# (HTTP on :8000, RPC bound to loopback only). The application source is
# bind-mounted at runtime (see docker-compose.yml), so nothing is copied in
# here.

FROM php:8.4-fpm-bookworm

ARG WWWUSER=1000
ARG WWWGROUP=1000

# Install system dependencies. nodejs/npm are required by `octane:start
# --watch` (the chokidar watcher is installed into the app's node_modules by
# the entrypoint when INSTALL_NODE_DEPS=1; chokidar must stay on v3 — v4+
# is ESM-only and breaks Octane's require()-based watcher).
RUN apt-get update && apt-get install -y \
    build-essential \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    locales \
    zip \
    unzip \
    git \
    curl \
    libzip-dev \
    libonig-dev \
    libxml2-dev \
    libcurl4-openssl-dev \
    libssl-dev \
    libicu-dev \
    libftp-dev \
    libpq-dev \
    nodejs \
    npm \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
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
    sockets

# Install PECL extensions
RUN pecl install redis \
    && docker-php-ext-enable redis

# Adjust user and group IDs to match host (to avoid permission issues)
RUN groupmod -g ${WWWGROUP} www-data \
    && usermod -u ${WWWUSER} www-data

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# RoadRunner binary, pinned to the exact version the app is tested against
# (spiral-packages in composer.lock + Octane's version checks). Lives on
# PATH outside the bind mount, so the mounted source never hides it.
# Matches the binary `vendor/bin/rr get-binary` puts at apps/backend/rr for
# host-side development.
COPY --from=ghcr.io/roadrunner-server/roadrunner:3.0.1 /usr/bin/rr /usr/local/bin/rr

COPY php.ini /usr/local/etc/php/conf.d/app.ini
COPY entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Set working directory
WORKDIR /var/www/html

# First-run bootstrap (composer install, .env, key, migrate) — idempotent.
# The startup-command contract mirrors production: explicit worker sizing
# and an RPC listener bound to loopback only (never 0.0.0.0).
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php", "artisan", "octane:start", "--host=0.0.0.0", "--port=8000", "--rpc-host=127.0.0.1", "--workers=4", "--max-requests=500", "--watch"]
