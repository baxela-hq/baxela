#!/bin/sh
set -e

# First-run bootstrap so a plain `docker compose up` yields a working API:
# install the composer vendor tree, create .env from its example, generate
# APP_KEY, and run migrations. Every step is idempotent, so later starts are
# cheap no-ops. The source (including vendor and .env) is bind-mounted from
# apps/backend, so this also works when a copy already exists on the host.
#
# app, queue and reverb all share this entrypoint. Two flags keep the
# side-effecting steps sole-owner (mirroring each other):
#   RUN_MIGRATIONS=1     only the compose `app` service may migrate — a
#                        worker restarting after its tables were wiped must
#                        not re-create them mid `module:migrate-fresh`.
#   INSTALL_NODE_DEPS=1  only the compose `app` service may install the
#                        backend's npm dependencies (chokidar for
#                        `octane:start --watch`) into the bind-mounted
#                        node_modules — queue/reverb must never race it.
cd /var/www/html

if [ ! -f vendor/autoload.php ] || [ composer.lock -nt vendor/autoload.php ]; then
    composer install --no-interaction --no-progress
fi

# Node deps for the Octane watcher. `npm ci` (not install) keeps the result
# deterministic against the committed package-lock.json; a failure here must
# abort startup rather than leave octane --watch without its watcher.
if [ "${INSTALL_NODE_DEPS:-0}" = "1" ]; then
    if [ ! -f node_modules/.package-lock.json ] || [ package-lock.json -nt node_modules/.package-lock.json ]; then
        npm ci --no-audit --no-fund
    fi
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
