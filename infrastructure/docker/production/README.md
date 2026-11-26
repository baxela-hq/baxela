# Production environment

Single-server deployment: backend (php-fpm + nginx), one-shot migrate,
queue worker, scheduler, admin SPA, storefront (Next.js standalone server),
PostgreSQL, Redis. Defined in
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

`up` starts PostgreSQL/Redis, runs the one-shot `migrate` service, then brings up
the backend (php-fpm + nginx), the outer nginx proxy, queue, scheduler, and
the admin panel. `backend`, `queue`, and `scheduler` wait for `migrate` to
complete, so workers never race migrations.

- Backend API: `http://<host>:${BACKEND_PORT:-8080}`
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

# Enter PostgreSQL (psql)
docker compose exec postgres psql -U baxela -d "$DB_DATABASE"

# Backup (custom format — compressed, pg_restore-able, parallel-safe)
docker compose exec postgres pg_dump -U baxela -d "$DB_DATABASE" -Fc \
    > backup-$(date +%F).pgdump

# Restore into a FRESH database (never in place — restore-then-swap)
docker compose exec postgres psql -U baxela -d postgres \
    -c "CREATE DATABASE ${DB_DATABASE}_restored"
docker compose exec -T postgres pg_restore -U baxela -d "${DB_DATABASE}_restored" \
    < backup-2026-09-01.pgdump

# Liveness probe for scripts
docker compose exec postgres pg_isready -U baxela -d "$DB_DATABASE"
```

## The backend web server (php-fpm + nginx)

The `backend` container runs php-fpm and an embedded nginx under supervisord
(`infrastructure/docker/production/backend/supervisord.conf`); the embedded
nginx listens on `:8000`, serves `public/` directly, and fastcgi-passes the
front controller to the local fpm pool. The outer `backend-nginx` proxy
reaches it through the isolated `proxy` network (`backend-upstream` alias);
`TRUSTED_PROXIES=172.28.0.0/29,127.0.0.1` in `.env.production` must stay in
sync with that subnet (127.0.0.1 covers the in-container nginx→fpm hop).

- **Deploys recreate the container** (`docker compose up -d --build`), which
  starts fresh processes. The images are immutable
  (`opcache.validate_timestamps=0`), so a reused container would keep
  serving old code — always deploy by recreating.
- **Extension lifecycle.** `baxela:install/enable/disable/remove` take
  effect on the next request: the framework boots per request under FPM, so
  the boot guard re-registers drivers/routes with current state. No reload
  step exists.
- **Pool sizing and memory.** The fpm pool keeps the stock process manager
  (dynamic, 5 children); override via
  `infrastructure/docker/production/backend/php-fpm-pool.conf` when measured
  load demands it. PHP's `memory_limit` (256M) bounds each request — the
  container budget is pool size × per-request peak.
- **Request timeout budget.** PHP's `max_execution_time` (30s) owns the
  request limit; the compose `stop_grace_period` (45s) exceeds it so
  in-flight requests survive shutdown. Scale both together.
- **Healthchecks.** The `backend` healthcheck probes the embedded web server
  directly (`127.0.0.1:8000/up`); the outer nginx check verifies the
  externally relevant request path. A failing backend check with a passing
  nginx check points at the app server, and vice versa.

## Uptime monitoring

Point your monitor (UptimeRobot, healthchecks.io, a cron curl, …) at:

```
GET http://<host>:${BACKEND_PORT:-8080}/api/v1/core/public/health
```

`200` means serving (database reachable); `503` means down. The payload
reports each component — database (critical), Redis, queue (driver, pending
and failed counts). Redis/queue failures alone only *degrade* (still 200:
realtime goes dark but checkout lives); append `?strict=1` if your monitor
should page on those too. `/up` also works but only proves the framework
booted — prefer the health endpoint.

## Failed jobs and queue schedules

Failed queued jobs surface in the admin panel under **Failed Jobs** (inspect,
retry, delete — each with its own permission) and every final job failure (or
queue-depth threshold crossing) alerts the admin inbox plus the configured
notification channels. Retry re-queues the job for another attempt and may
repeat its side effects — verify the original operation first. See
`apps/backend/docs/flows/queue-visibility.md`.

Verify the scheduler actually executes the registered schedules:

```sh
docker compose -f docker-compose.prod.yml exec scheduler php artisan schedule:list
# expect: core:queue-check-backlog (every 5 minutes, without-overlapping)
#         queue:prune-failed --hours=720 (daily, permanent deletion)
```

## Notes

- **TLS is not terminated here.** Put a reverse proxy (Caddy, Traefik, nginx,
  cloud LB) in front of the published `BACKEND_PORT`/`ADMIN_PORT` for HTTPS.
  The proxy **must**:
  - terminate TLS for the API, admin, and storefront hostnames and redirect
    plain HTTP to HTTPS — the compose stack itself listens on plain HTTP, and
    bearer tokens cross the wire unencrypted without it;
  - set `Strict-Transport-Security` for the API and admin hostnames (the
    storefront already sends HSTS itself via `next.config.ts` headers);
  - forward the original protocol via `X-Forwarded-Proto` so the backend can
    generate correct absolute URLs.
  A future gateway may replace this layer.
- **Security-relevant env vars** (see `.env.production.example`):
  - `CORS_ALLOWED_ORIGINS` — the exact browser origins (storefront + admin)
    allowed to call the API. No wildcard fallback exists; setting this is
    mandatory.
  - `ADMIN_USER_PASSWORD` — password for the staff accounts created by the
    Auth seeder. Required on first deploy; change it in-app afterwards.
  - `SESSION_SECURE_COOKIE=true` — cookies only over TLS.
- PostgreSQL has **no published host port** by default; reach it through the
  compose network or add an explicit `ports:` entry if needed.
- The admin SPA's `VITE_*` values and the storefront's `NEXT_PUBLIC_*` values
  are build args — changing them requires `docker compose build admin` /
  `docker compose build storefront` (they are baked into the JS bundle). The
  storefront's server-side fetches use the runtime `SERVER_API_BASE_URL`.
- The storefront runs the Next.js standalone server (`server.js`) built by
  `infrastructure/docker/production/storefront/`.
