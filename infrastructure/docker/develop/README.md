# Develop environment

php-fpm + nginx + MySQL + Redis for hacking on the backend, with the
application source bind-mounted from `apps/backend` — no host PHP needed.

Defined in [`docker-compose.yml`](../../../docker-compose.yml) at the repo
root (this directory only holds the build assets: the backend Dockerfile,
php.ini, and the nginx vhost).

## Quickstart

From the repo root:

```bash
cp .env.example .env                       # adjust ports / DB creds if needed
docker compose up -d
```

Then, still from the repo root (commands run inside the container):

```bash
# Install dependencies (bind mount makes apps/backend the container's workdir)
docker compose exec app composer install

# Configure the Laravel app (defaults match the root .env.example)
cd apps/backend && cp .env.example .env
cd ../..
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
```

The API is served at `http://localhost:${BACKEND_APP_PORT:-8085}` — check it
with `curl http://localhost:8085/up`.

## Services

| Service | Image | Notes |
|---------|-------|-------|
| `app` | `baxela/backend:develop` (built from `infrastructure/docker/develop/backend`) | php-fpm, `www-data` uid/gid matched to host via `WWWUSER`/`WWWGROUP` |
| `web` | `nginx:stable-bookworm` | Laravel fpm vhost from `infrastructure/docker/develop/nginx/backend.conf` |
| `mysql` | `mysql:8-debian` | credentials from `DEV_DB_*` in `.env`; host port `3308` |
| `redis` | `redis:7-bookworm` | host port `6378` |
| `mail` | `axllent/mailpit` | opt-in: `docker compose --profile mail up -d`, UI on port `8025` |

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
docker compose down -v   # stops everything and drops the MySQL/Redis volumes
```
