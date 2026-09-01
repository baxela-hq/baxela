#!/bin/sh
set -e

cd /var/www/html

# package:discover is normally run by composer's post-autoload-dump script,
# which we skip at build time (--no-scripts) so the image stays env-agnostic.
php artisan package:discover --ansi
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

exec docker-php-entrypoint "$@"
