# Production environment

Single-server deployment: backend (Laravel Octane + RoadRunner behind nginx),
one-shot migrate, queue worker, scheduler, admin SPA, storefront (Next.js
standalone server), MySQL, Redis. Defined in
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
Octane (RoadRunner), nginx, queue, scheduler, and the admin panel. `backend`,
`queue`, and `scheduler` wait for `migrate` to complete, so workers never
race migrations.

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

# Enter MySQL
docker compose exec mysql mysql -u root -p

# Backup / restore
docker compose exec mysql sh -c 'exec mysqldump -u root -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"' \
    | gzip > backup-$(date +%F).sql.gz
gunzip -c backup-2026-09-01.sql.gz | docker compose exec -T mysql sh -c 'exec mysql -u root -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"'
```

## The Octane server (RoadRunner)

The `backend` container runs `php artisan octane:start --host=0.0.0.0
--port=8000 --rpc-host=127.0.0.1 --workers=4 --max-requests=500`. The RPC
listener is bound to loopback only and never published. nginx reaches the
HTTP listener through the isolated `proxy` network (`backend-upstream`
alias); `TRUSTED_PROXIES=172.28.0.0/29` in `.env.production` must stay in
sync with that subnet.

- **Fresh container vs reused container.** A deploy that recreates the
  container (`docker compose up -d --build`) starts fresh workers — nothing
  to do. If a container is ever reused after code or config changes, workers
  still run the old code in memory (`opcache.validate_timestamps=0`):
  `docker compose exec backend php artisan octane:reload` (graceful worker
  replacement) or restart the container.
- **Extension lifecycle.** `baxela:install/enable/disable/remove` run in a
  separate CLI process and cannot reach running HTTP workers. After changing
  an extension, run `php artisan octane:reload` (the extension's registered
  driver/routes update on the replacement workers; the admin API's extension
  *state* is always current because it re-reads per request).
- **Worker sizing and memory.** `--workers` and `--max-requests` are baked
  into the image command; resize via a compose `command:` override rather
  than rebuilding. `max_worker_memory` (soft per-worker recycle limit) lives
  in `apps/backend/.rr.yaml` — raise it only after measuring worker peaks
  under real load (large catalog responses, invoice PDF generation, image
  processing); a per-worker limit is not a container memory budget.
- **Request timeout budget.** `max_execution_time` in `config/octane.php`
  owns the request limit (RoadRunner's `exec_ttl` is derived from it), nginx
  `proxy_read_timeout` must not be lower, and the compose
  `stop_grace_period` must exceed it so in-flight requests survive shutdown.
  Scale all three together.
- **Healthchecks.** The `backend` healthcheck probes RoadRunner directly
  (`127.0.0.1:8000/up`); the nginx check verifies the externally relevant
  request path. A failing backend check with a passing nginx check points at
  the app server, and vice versa.

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
- MySQL has **no published host port** by default; reach it through the
  compose network or add an explicit `ports:` entry if needed.
- The admin SPA's `VITE_*` values and the storefront's `NEXT_PUBLIC_*` values
  are build args — changing them requires `docker compose build admin` /
  `docker compose build storefront` (they are baked into the JS bundle). The
  storefront's server-side fetches use the runtime `SERVER_API_BASE_URL`.
- The storefront runs the Next.js standalone server (`server.js`) built by
  `infrastructure/docker/production/storefront/`.
