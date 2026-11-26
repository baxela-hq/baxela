#!/bin/sh
set -e

# First-run bootstrap so a plain `docker compose up` yields a working API:
# install the composer vendor tree, create .env from its example, generate
# APP_KEY, and run migrations. Every step is idempotent, so later starts are
# cheap no-ops. The source (including vendor and .env) is bind-mounted from
# apps/backend, so this also works when a copy already exists on the host.
#
# app, queue and reverb all share this entrypoint, but only the compose
# service carrying RUN_MIGRATIONS=1 may migrate: a worker restarting after
# its tables were wiped must not re-create them mid `module:migrate-fresh`.
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

if [ "${RUN_MIGRATIONS:-0}" = "1" ]; then
    php artisan migrate --force --no-interaction
fi

# public/storage must be a *relative* symlink: artisan storage:link writes
# an absolute path that only resolves where it ran (host or container), so a
# link created on the host dangles inside the bind mount and nginx returns
# 403 for every /storage file. ../storage/app/public resolves in both.
ln -sfn ../storage/app/public public/storage

exec "$@"
