# Contributing to Baxela

Thanks for considering a contribution!

## Getting started

1. Clone and boot the dev stack from the repo root:

   ```bash
   cp .env.example .env
   docker compose up -d
   ```

   Backend on `:8085`, admin on `:5173`, storefront on `:3000`. Stack details:
   [`infrastructure/docker/develop/README.md`](infrastructure/docker/develop/README.md).

2. Install the git hooks so commit messages are validated locally:

   ```bash
   sh scripts/install-hooks.sh
   ```

3. Read the `AGENTS.md` at the repo root and in the app you're touching — they carry the
   conventions (module structure, feature layout, known gotchas) for humans and AI
   contributors alike.

## Commit messages

Conventional commits, enforced by the `commit-msg` hook:
`<type>(<scope>): <subject>` — scopes are `backend`, `admin`, `storefront`, `api`,
`infra`, or root scopes (`monorepo`, `deps`, `compose`). The full spec:
[`docs/COMMIT_CONVENTION.md`](docs/COMMIT_CONVENTION.md).

## Before opening a PR

- **Backend** (`apps/backend`): `composer test` (Pest, SQLite in-memory). New endpoints
  need feature tests — follow the patterns in `Modules/{Module}/tests/`.
- **Admin** (`apps/admin`): `pnpm build && pnpm lint` (the build is the typecheck).
- **Storefront** (`apps/storefront`): `pnpm build`.
- **Translations**: all locales must stay in parity — `node scripts/check-i18n-parity.mjs`
  runs in CI and fails on missing or extra keys.
- Your PR title should pass the same commit-message convention (CI validates every
  commit subject).

## Translations

Locale files live in `apps/storefront/messages/{locale}/` and
`apps/admin/public/locales/{locale}/`. A new locale = a full copy of the `en` tree with
identical keys (the parity script enforces this). See the roadmap's phase plans for the
current locale priorities: [`docs/ROADMAP.md`](docs/ROADMAP.md).

## Where things are planned

The public roadmap and its per-phase execution plans live in
[`docs/ROADMAP.md`](docs/ROADMAP.md) and [`docs/plans/`](docs/plans/) — pick an issue
labeled `good-first-issue` if it's your first contribution.
