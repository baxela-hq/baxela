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
```

Each app keeps its own `AGENTS.md` with app-specific conventions (read the relevant one before touching that app).

## Git Conventions

- Conventional commits: `type(scope): subject` — types: `feat`, `fix`, `refactor`, `chore`, `test`, `docs`, `build`, `ci`.
- **Scope is the app** the change belongs to: `backend`, `admin`, `storefront`, `api`, or `infra` for root-level changes (compose files, root config, etc.).
- For a deeper change, append the app's module/feature after a slash — e.g. `feat(backend/catalog): ...` or `fix(admin/auth): ...`. The app scope stays primary so a commit's target is identifiable from the subject alone.
- Examples:
  - `feat(backend/catalog): add product shipping dimensions`
  - `fix(admin): handle expired tokens on sign-in`
  - `chore(api): add Bruno collection for media endpoints`
  - `infra(backend): add mysql healthcheck to compose`
- Only commit when explicitly asked.