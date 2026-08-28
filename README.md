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
├── .github/              # CI / PR templates
├── docker-compose.yml    # Dev only; build contexts -> ./apps/backend
├── .env.example          # Compose-level vars only (ports etc.)
└── README.md
```

## Quick start

1. Copy environment: `cp .env.example .env`
2. Start dev services: `docker compose up -d`
3. See `apps/<app>/README.md` for per-app setup.

## Layout guidelines

- Each app owns its code, config, and its own `.env`.
- The root `.env` holds only compose/infra-level variables (ports, user ids).
- The root `docker-compose.yml` is development-only and builds from `./apps/backend`.
- Cross-project documentation lives in `docs/`.

## Contributing

- Follow the [commit message convention](docs/COMMIT_CONVENTION.md): `<type>(<scope>): <subject>` with a mandatory scope.

## Moving apps

Each app was moved as-is into `apps/` preserving its git history and existing config
(e.g. `apps/backend` keeps its `docker/` and `AGENTS.md`).
