#!/bin/sh
set -e

# Install dependencies on first start and after lockfile changes, so the
# stack comes up with a plain `docker compose up`. node_modules lives on an
# anonymous volume (docker-compose.yml maps /app/node_modules) and survives
# restarts; the stamp file lives inside it, away from the bind-mounted source.
if [ ! -f node_modules/.pnpm-install-stamp ] || [ pnpm-lock.yaml -nt node_modules/.pnpm-install-stamp ]; then
    pnpm install --frozen-lockfile
    touch node_modules/.pnpm-install-stamp
fi

exec "$@"
