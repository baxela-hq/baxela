# Production environment

Single-server deployment: backend (php-fpm + nginx), one-shot migrate,
queue worker, scheduler, admin SPA, MySQL, Redis. Images are built from the
**repo root** context, so they are self-contained and reusable as-is in
Kubernetes later. Runtime configuration comes from `.env` via `env_file` —
nothing secret is baked into the images.

## First run

```bash
cd infrastructure/docker/production
cp .env.example .env        # then edit: DB creds, APP_URL, VITE_API_BASE_URL, ...

# Generate an APP_KEY and put it in .env
docker compose run --rm --no-deps --entrypoint php migrate artisan key:generate --show

docker compose up -d --build
```

`up` starts MySQL/Redis, runs the one-shot `migrate` service, then brings up
fpm, nginx, queue, scheduler, and the admin panel. `backend`, `queue`, and
`scheduler` wait for `migrate` to complete, so workers never race migrations.

- Backend API: `http://<host>:${BACKEND_PORT:-8080}` (health: `/up`)
- Admin panel: `http://<host>:${ADMIN_PORT:-8081}`

## Day-2 operations

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
- The admin SPA's `VITE_*` values are build args — changing them requires
  `docker compose build admin` (they are baked into the JS bundle).
- The storefront does not have a production image yet; one will be added
  under `production/storefront/` when the app becomes real.
