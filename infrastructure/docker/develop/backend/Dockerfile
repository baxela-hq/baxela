# Development php-fpm image. The application source is bind-mounted at
# runtime (see docker-compose.yml), so nothing is copied in here.

FROM php:8.4-fpm-bookworm

ARG WWWUSER=1000
ARG WWWGROUP=1000

# Install system dependencies
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

COPY php.ini /usr/local/etc/php/conf.d/app.ini
COPY entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Set working directory
WORKDIR /var/www/html

# First-run bootstrap (composer install, .env, key, migrate) — idempotent.
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm"]
