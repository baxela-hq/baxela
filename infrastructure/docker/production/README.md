# Production environment

Single-server deployment: backend (php-fpm + nginx), one-shot migrate,
queue worker, scheduler, admin SPA, storefront (Next.js standalone server),
MySQL, Redis. Defined in
[`docker-compose.prod.yml`](../../../docker-compose.prod.yml) at the repo
root; this directory only holds the build assets (Dockerfiles, nginx configs,
php.ini, entrypoint). Images are built from the **repo root** context, so
they are self-contained and reusable as-is in Kubernetes later. Runtime
configuration comes from `.env.production` via `env_file` — nothing secret is
baked into the images.

> On the server, a bare `docker compose up -d` (no `-f`) would start the
> **develop** stack. Always pass `--env-file .env.production -f
> docker-compose.prod.yml` in production.

## First run

From the repo root:

```bash
cp .env.production.example .env.production   # then edit: DB creds, APP_URL, VITE_API_BASE_URL, ...

# Generate an APP_KEY and put it in .env.production
docker compose --env-file .env.production -f docker-compose.prod.yml \
    run --rm --no-deps --entrypoint php migrate artisan key:generate --show

docker compose --env-file .env.production -f docker-compose.prod.yml up -d --build
```

`up` starts MySQL/Redis, runs the one-shot `migrate` service, then brings up
fpm, nginx, queue, scheduler, and the admin panel. `backend`, `queue`, and
`scheduler` wait for `migrate` to complete, so workers never race migrations.

- Backend API: `http://<host>:${BACKEND_PORT:-8080}` (health: `/up`)
- Admin panel: `http://<host>:${ADMIN_PORT:-8081}`
- Storefront: `http://<host>:${STOREFRONT_PORT:-8082}`

## Day-2 operations

All `docker compose` commands below are short for
`docker compose --env-file .env.production -f docker-compose.prod.yml`.

```bash
# Deploy a new version
git pull && docker compose up -d --build

# Tail logs (any service name works: backend, queue, scheduler, ...)
docker compose logs -f backend

# Run a one-off artisan command
docker compose run --rm --no-deps --entrypoint php backend artisan <command>
# (the entrypoint is skipped via --entrypoint; caching already ran at build)

# Enter MySQL
docker compose exec mysql mysql -u root -p

# Backup / restore
docker compose exec mysql sh -c 'exec mysqldump -u root -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"' \
    | gzip > backup-$(date +%F).sql.gz
gunzip -c backup-2026-09-01.sql.gz | docker compose exec -T mysql sh -c 'exec mysql -u root -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"'
```

## Notes

- **TLS is not terminated here.** Put a reverse proxy (Caddy, Traefik, nginx,
  cloud LB) in front of the published `BACKEND_PORT`/`ADMIN_PORT` for HTTPS.
  A future gateway may replace this layer.
- MySQL has **no published host port** by default; reach it through the
  compose network or add an explicit `ports:` entry if needed.
- The admin SPA's `VITE_*` values and the storefront's `NEXT_PUBLIC_*` values
  are build args — changing them requires `docker compose build admin` /
  `docker compose build storefront` (they are baked into the JS bundle). The
  storefront's server-side fetches use the runtime `SERVER_API_BASE_URL`.
- The storefront runs the Next.js standalone server (`server.js`) built by
  `infrastructure/docker/production/storefront/`.
