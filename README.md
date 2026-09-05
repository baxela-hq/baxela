# Baxela

Monorepo for the Baxela e-commerce platform. It bundles the backend, admin panel, and storefront frontend plus shared API collections and cross-project documentation.

## Structure

```
baxela/
├── apps/
│   ├── backend/          # API / backend (Laravel)
│   ├── admin/            # Admin panel (from xshop-admin)
│   └── storefront/       # Next.js storefront
├── api/
│   └── bruno/            # Bruno API collections
├── docs/                 # Cross-project documentation
├── infrastructure/
│   └── docker/           # Docker build assets per environment (Dockerfiles, nginx, php.ini)
├── .github/              # CI / PR templates
├── docker-compose.yml    # Develop stack (default): docker compose up -d
├── docker-compose.prod.yml # Production stack (single-server deploy)
└── README.md
```

## Quick start

1. Start the dev stack from the repo root: `cp .env.example .env && docker compose up -d`
   — backend on `:8085`, admin on `:5173`, storefront on `:3000`. The
   frontends are the `frontend` compose profile, on by default; set
   `COMPOSE_PROFILES=` (empty) in `.env` for a backend-only stack. First-time
   Laravel setup (composer install, key, migrate) runs automatically.
2. See the develop [README](infrastructure/docker/develop/README.md) for
   stack details (services, profiles, mailpit, reset).
3. Frontend dev servers can also run on the host — see `apps/<app>/README.md`.

## Layout guidelines

- Each app owns its code, config, and its own `.env`.
- The compose entrypoints live at the repo root — `docker-compose.yml` for
  development (the default), `docker-compose.prod.yml` for production. Their
  build assets (Dockerfiles, nginx configs, php.ini) live in
  `infrastructure/docker/<environment>/`.
- Cross-project documentation lives in `docs/`.

## Contributing

- Follow the [commit message convention](docs/COMMIT_CONVENTION.md): `<type>(<scope>): <subject>` with a mandatory scope.

## Moving apps

Each app was moved as-is into `apps/` preserving its git history and existing config
(e.g. `apps/backend` keeps its `AGENTS.md`).
