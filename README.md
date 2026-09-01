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
│   └── docker/           # Per-environment compose setups (develop/, production/)
├── .github/              # CI / PR templates
└── README.md
```

## Quick start

1. Start the dev stack: `cd infrastructure/docker/develop && cp .env.example .env && docker compose up -d`
2. Follow the develop [README](infrastructure/docker/develop/README.md) for the
   first-time Laravel setup (composer install, key, migrate).
3. See `apps/<app>/README.md` for per-app setup.

## Layout guidelines

- Each app owns its code, config, and its own `.env`.
- Docker assets live in `infrastructure/docker/<environment>/` — there is no
  root compose file; each environment is run from its own directory.
- Cross-project documentation lives in `docs/`.

## Contributing

- Follow the [commit message convention](docs/COMMIT_CONVENTION.md): `<type>(<scope>): <subject>` with a mandatory scope.

## Moving apps

Each app was moved as-is into `apps/` preserving its git history and existing config
(e.g. `apps/backend` keeps its `AGENTS.md`).
