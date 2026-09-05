#!/bin/sh
set -e

# First-run bootstrap so a plain `docker compose up` yields a working API:
# install the composer vendor tree, create .env from its example, generate
# APP_KEY, and run migrations. Every step is idempotent, so later starts are
# cheap no-ops. The source (including vendor and .env) is bind-mounted from
# apps/backend, so this also works when a copy already exists on the host.
cd /var/www/html

if [ ! -f vendor/autoload.php ] || [ composer.lock -nt vendor/autoload.php ]; then
    composer install --no-interaction --no-progress
fi

if [ ! -f .env ]; then
    cp .env.example .env
fi

if ! grep -Eq '^APP_KEY=.+$' .env; then
    php artisan key:generate --force --no-interaction
fi

php artisan migrate --force --no-interaction

exec "$@"
