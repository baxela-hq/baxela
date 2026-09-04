# AGENTS.md

Guidelines for AI agents working in this repository. Read this before making changes.

## Repository Overview

**Baxela** — monorepo hosting an e-commerce platform as multiple apps:

```
apps/
  backend/     # Laravel API (modular monolith)
  admin/       # React + Vite admin panel
  storefront/  # (upcoming)
api/           # Bruno API collections
docs/          # Documentation
infrastructure/
  docker/      # Docker build assets per environment (Dockerfiles, nginx, php.ini)
docker-compose.yml         # Develop stack (default): docker compose up -d
docker-compose.prod.yml    # Production stack (single-server deploy)
```

## Agent Guidelines Index

Each app keeps its own `AGENTS.md` with app-specific conventions. Read the relevant one before touching that app:

- `apps/backend/AGENTS.md` — Laravel/backend conventions: module structure, Action/Controller patterns, error handling, tests.
- `apps/admin/AGENTS.md` — React/TypeScript conventions: feature layout, data tables, i18n, commit tooling.
- `apps/storefront/AGENTS.md` — not created yet (app is upcoming).

The **Git Conventions** below are monorepo-level and take precedence over per-app `AGENTS.md` sections that define their own commit-scope rules.

## Git Conventions

- Conventional commits: `type(scope): subject` — types: `feat`, `fix`, `refactor`, `chore`, `test`, `docs`, `build`, `ci`.
- **Scope is the app** the change belongs to: `backend`, `admin`, `storefront`, `api`, or `infra` for `infrastructure/` and other root-level changes (root config, CI, etc.).
- For a deeper change, append the app's module/feature after a slash — e.g. `feat(backend/catalog): ...` or `fix(admin/auth): ...`. The app scope stays primary so a commit's target is identifiable from the subject alone.
- Examples:
  - `feat(backend/catalog): add product shipping dimensions`
  - `fix(admin): handle expired tokens on sign-in`
  - `chore(api): add Bruno collection for media endpoints`
  - `infra(backend): add mysql healthcheck to compose`
- Only commit when explicitly asked.