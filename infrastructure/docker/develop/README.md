# Develop environment

php-fpm + nginx + MySQL + Redis for hacking on the backend, plus Vite /
Next.js dev servers for the admin panel and storefront — application sources
are bind-mounted from `apps/`, so no host PHP or Node is required.

Defined in [`docker-compose.yml`](../../../docker-compose.yml) at the repo
root (this directory only holds the build assets: the per-app Dockerfiles,
php.ini, and the nginx vhost).

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
| `app` | `baxela/backend:develop` (built from `infrastructure/docker/develop/backend`) | php-fpm, `www-data` uid/gid matched to host via `WWWUSER`/`WWWGROUP` |
| `web` | `nginx:stable-bookworm` | Laravel fpm vhost from `infrastructure/docker/develop/nginx/backend.conf` |
| `admin` | `baxela/admin:develop` (built from `infrastructure/docker/develop/admin`) | `frontend` profile (on by default): Vite dev server, host port `5173` |
| `storefront` | `baxela/storefront:develop` (built from `infrastructure/docker/develop/storefront`) | `frontend` profile (on by default): Next.js dev server, host port `3000` |
| `mysql` | `mysql:8-debian` | credentials from `DEV_DB_*` in `.env`; host port `3308` |
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

## Reset

```bash
docker compose down -v   # stops everything; drops the MySQL/Redis volumes and
                         # the frontends' node_modules (reinstalled on next up)
```
