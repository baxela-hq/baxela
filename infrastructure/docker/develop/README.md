# Develop environment

php-fpm + nginx + PostgreSQL + Redis for hacking on the backend, plus Vite /
Next.js dev servers for the admin panel and storefront — application sources
are bind-mounted from `apps/`, so no host PHP or Node is required.

Defined in [`docker-compose.yml`](../../../docker-compose.yml) at the repo
root (this directory only holds the build assets: the per-app Dockerfiles,
php.ini, and the nginx vhost).

## Instant search (Meilisearch, `search` profile)

Search is optional and env-gated: with `MEILI_MASTER_KEY` empty the backend
uses the same SQL filtering as always. To enable:

1. Start the engine: `docker compose --profile search up -d`
2. Generate a master key (`openssl rand -hex 32`) and set `MEILI_MASTER_KEY`
   in the root `.env`, plus the same value as `MEILISEARCH_HOST`/
   `MEILISEARCH_MASTER_KEY` in `apps/backend/.env` (`MEILISEARCH_HOST=http://
   search:7700` — the compose service name, not localhost).
3. Build the index: `docker compose exec app php artisan catalog:search:install`
   then `catalog:reindex`.
4. Verify: a typo'd product title still matches on the storefront; the
   backend log line shows `engine=meili`. Stop the search container and
   requests fall back to SQL (log line `engine=sql`, warning recorded).

Upgrade policy: the image version is pinned via `MEILISEARCH_IMAGE`; bump it
deliberately and run a full `catalog:reindex` afterwards. Never reuse a
generation number — failed builds consume theirs permanently.

## Running two stacks side by side

A second develop stack (e.g. a clean-clone check) needs its own ports **and**
its own proxy subnet — the default `172.28.0.0/29` is pinned for the
nginx → backend trusted-proxy path. In the second clone's root `.env` set
`COMPOSE_PROJECT_NAME`, shifted `*_PORT`/`BACKEND_*_PORT` values, and the
`PROXY_SUBNET` / `PROXY_GATEWAY` / `TRUSTED_PROXIES` triplet (keep
`TRUSTED_PROXIES` equal to `PROXY_SUBNET`) — see `.env.example`.

## Quickstart

From the repo root:

```bash
cp .env.example .env                       # adjust ports / DB creds if needed
docker compose up -d
```

This starts the backend stack plus (via `COMPOSE_PROFILES=frontend`, the
`.env.example` default) the admin and storefront dev servers — see
[Frontend dev servers](#frontend-dev-servers) below.

The `app` container's entrypoint bootstraps Laravel on first start (composer
install, `.env` from its example, `APP_KEY`, migrations) and skips settled
steps on later starts, so no manual setup is required. The API is served at
`http://localhost:${BACKEND_APP_PORT:-8085}` — check it with
`curl http://localhost:8085/up` (the `web` service healthcheck uses the same
route).

## Services

| Service | Image | Notes |
|---------|-------|-------|
| `app` | `baxela/backend:develop` (built from `infrastructure/docker/develop/backend`) | php-fpm (pool on `:9000`, reached only over the `proxy` network); `www-data` uid/gid matched to host via `WWWUSER`/`WWWGROUP`; sole owner of migrations (`RUN_MIGRATIONS=1`) |
| `web` | `nginx:stable-bookworm` | Laravel fpm vhost (static files served directly, everything else fastcgi-passed) from `infrastructure/docker/develop/nginx/backend.conf` |
| `admin` | `baxela/admin:develop` (built from `infrastructure/docker/develop/admin`) | `frontend` profile (on by default): Vite dev server, host port `5173` |
| `storefront` | `baxela/storefront:develop` (built from `infrastructure/docker/develop/storefront`) | `frontend` profile (on by default): Next.js dev server, host port `3000` |
| `postgres` | `postgres:18-alpine` | credentials from `DEV_DB_*` in `.env`; host port `5433` |
| `redis` | `redis:7-bookworm` | host port `6378` |
| `mail` | `axllent/mailpit` | opt-in: `docker compose --profile mail up -d`, UI on port `8025` |

## Frontend dev servers

The `admin` (Vite) and `storefront` (Next.js) services belong to the
`frontend` compose profile, enabled by default through `COMPOSE_PROFILES` in
the root `.env.example` — so a plain `docker compose up -d` starts the whole
platform:

| App | URL |
|-----|-----|
| Backend API | http://localhost:8085 |
| Admin panel | http://localhost:5173 |
| Storefront | http://localhost:3000 |

Notes:

- On first start each container runs `pnpm install --frozen-lockfile`
  automatically (a few minutes); subsequent starts skip it. `node_modules`
  lives on an anonymous volume and survives restarts.
- Browser code reaches the API through the host-published port
  (`VITE_API_BASE_URL` / `NEXT_PUBLIC_API_BASE_URL` =
  `http://localhost:8085/api/v1`), while the storefront's server-side
  fetches use `SERVER_API_BASE_URL` (`http://web:80/api/v1`) on the compose
  network.
- For a backend-only stack, set `COMPOSE_PROFILES=` (empty) in the root
  `.env`. A one-off full stack also works:
  `docker compose --profile frontend up -d`.
- Running the dev servers on the host (`pnpm dev` inside `apps/<app>`)
  remains fully supported — see each app's README.

## Mailpit

Start it alongside the stack and point Laravel at it:

```bash
docker compose --profile mail up -d
# apps/backend/.env
# MAIL_MAILER=smtp
# MAIL_HOST=mail
# MAIL_PORT=1025
```

## The php-fpm server

The `app` service runs plain `php-fpm` (pool on `:9000`, the base image
default). The framework boots per request, so there is no reload dance:

- **Code, config and `.env` changes apply on the next request** — nothing to
  restart or reload.
- **Extension lifecycle commands** (`baxela:install/enable/disable`) take
  effect on the next request too; the registered driver/routes re-register
  per boot.
- The nginx vhost serves existing `public/` files directly and fastcgi-passes
  everything else to the fpm pool over the isolated `proxy` network
  (`app-upstream` alias, `TRUSTED_PROXIES=172.28.0.0/29`). Requests for
  `*.php`/`*.phtml`/`*.phar` outside the front controller are rejected (404) —
  uploaded PHP never executes or discloses source.

## Reset

```bash
docker compose down -v   # stops everything; drops the PostgreSQL/Redis volumes and
                         # the frontends' node_modules (reinstalled on next up)
```

## Seeding

The master `DatabaseSeeder` is intentionally empty — seed per module, in
dependency order:

```bash
docker compose exec app php artisan db:seed --class="Modules\Core\Database\Seeders\CoreDatabaseSeeder"
docker compose exec app php artisan db:seed --class="Modules\Auth\Database\Seeders\AuthDatabaseSeeder"
docker compose exec app php artisan db:seed --class="Modules\Setting\Database\Seeders\SettingDatabaseSeeder"
docker compose exec app php artisan db:seed --class="Modules\Media\Database\Seeders\MediaDatabaseSeeder"
docker compose exec app php artisan db:seed --class="Modules\Payment\Database\Seeders\PaymentDatabaseSeeder"
docker compose exec app php artisan db:seed --class="Modules\Shipping\Database\Seeders\ShippingDatabaseSeeder"
docker compose exec app php artisan db:seed --class="Modules\Catalog\Database\Seeders\CatalogDatabaseSeeder"
docker compose exec app php artisan db:seed --class="Modules\Tax\Database\Seeders\TaxDatabaseSeeder"
```

Core first (languages/countries/currencies), then Auth (the catalog/content media
seeders need a real staff actor for `media.user_id`), then **Media before Catalog** —
the product seeder needs the `Catalog/Product` media folder tree. The e2e suite
(`apps/storefront`, `pnpm test:e2e`) expects this seed plus Mailpit running, and
completes checkout through the manual payment method the Payment seeder activates.
