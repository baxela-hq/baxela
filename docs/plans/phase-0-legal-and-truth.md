# Phase 0 Plan — Legal & Truth (Week 1, ~2–3 days)

> Execution plan for Phase 0 of `docs/ROADMAP.md`. Self-contained: verified facts carry
> file paths; re-verify line numbers before editing (they drift).
> Conventions: conventional commits (`docs(monorepo): ...`-style), never commit unless
> the owner explicitly asks. Work top to bottom; tasks 0.1–0.3 are strict order, the rest
> is parallelizable.
>
> **Exit criteria:** repo legally usable · zero false claims in README/docs · secrets audit
> recorded with a CI guard · governance files in place, upstream template governance removed
> · repo page polished.

## Verified state (2026-10-21)

- **19 modules** exist under `apps/backend/Modules/`: Audit, Auth, Cart, Catalog, Contact,
  Content, Core, Discount, Inventory, Media, Menu, Notification, Order, Payment, Setting,
  Shipping, Support, Tax, User.
- Governance files: **none at repo root** (no LICENSE, SECURITY.md, CONTRIBUTING.md,
  CHANGELOG.md, code-of-conduct, issue templates). Root `.github/` has only `.gitkeep` and
  `workflows/ci.yml`.
- **Upstream shadcn-admin governance files sit inside `apps/admin/.github/`** (all
  git-tracked, all upstream's): `CODE_OF_CONDUCT.md`, `CONTRIBUTING.md`,
  `FUNDING.yml` (`github: [satnaing]`, `buy_me_a_coffee: satnaing`),
  `ISSUE_TEMPLATE/config.yml` + emoji-named `✨-feature-request.md` / `🐞-bug-report.md`,
  `PULL_REQUEST_TEMPLATE.md`, `workflows/ci.yml` (upstream CI), and `workflows/stale.yml`
  (scheduled cron that auto-closes issues — dangerous once public).
- `apps/admin/LICENSE` is upstream MIT (Sat Naing, 2024) — **must remain** (permitted
  removal of copyright notice requires authorization; we keep it).
- Secrets: audit performed 2026-10-21 — only the 6 `.env.example` files are tracked, all
  placeholders (`change-me`, dev `password`, blank DSNs); real env files are gitignored;
  no hardcoded Sentry DSNs (env-only); the sole literal DSN in tree is a test fixture at
  `apps/backend/Modules/Core/tests/Feature/Sentry/ErrorReportingTest.php:22`.
- Overclaims found: root `README.md:17` (Scramble bullet), `apps/backend/README.md:10`
  ("Fully documented (API, flows, ERD)"), `apps/backend/AGENTS.md:22` (Scramble claim),
  `apps/storefront/lib/currency.ts` docstring (claims a `CurrencyProvider` hook that does
  not exist).
- Dead doc links: `apps/backend/README.md` lines 21–25 + 37 reference `/docs/architecture.md`,
  `/docs/database`, `/docs/api`, `/docs/deployment` — none exist. `docs/README.md:8` links
  `architecture.md` (non-existent). What DOES exist: `apps/backend/docs/modules.md`,
  `apps/backend/docs/flows/` (4 flow docs), `docs/COMMIT_CONVENTION.md`, `docs/LOGGING.md`,
  `docs/TELEGRAM_NOTIFICATIONS.md`, `docs/THIRD_PARTY_SERVICES.md`.
- `apps/backend/docs/modules.md` describes an aspirational structure (`Identity`, `Pricing`
  don't exist). `apps/backend/docs/v1/modules.md` lists 12 modules — missing Audit, Discount,
  Menu, Notification, Support, Tax (Contact has a body section but is missing from the tree).
- CI (`.github/workflows/ci.yml`): 5 jobs — commit-lint, i18n-parity, backend (Pest on
  SQLite), admin (build+lint), storefront (build).

---

## Task 0.1 — Root LICENSE (MIT) — **BLOCKER, do first**

**Assumption flagged:** MIT chosen (max adoption; matches Bagisto/Velstore; compatible
with `apps/admin`'s upstream MIT). Owner can veto → swap text to GPL-3.0 before commit.

Steps:
1. Create `LICENSE` at repo root — MIT, copyright holder `Baxela` (confirm exact legal
   name with owner; default to `Baxela contributors`), year 2026.
2. Add a "License" section to root `README.md` (MIT, link to LICENSE) and note that
   `apps/admin` contains upstream shadcn-admin code under its own MIT license (keep file).
3. If admin README doesn't already credit upstream, add a "Credits" line in
   `apps/admin/README.md` pointing to https://github.com/satnaing/shadcn-admin.

Files: `LICENSE` (new), `README.md`, `apps/admin/README.md`.

Verify: `LICENSE` renders on GitHub (repo root shows "MIT license" badge).

Suggested commit: `docs(monorepo): add mit license and credits`

## Task 0.2 — README / docs truth pass

Every public claim must be true on launch day. Exact edits:

1. Root `README.md:17` — remove the Scramble bullet now (re-add when Phase 1 §1.6 wires
   it): change to "query filtering with spatie/laravel-query-builder" only.
2. `apps/backend/README.md:10` — replace "Fully documented (API, flows, ERD)" with the
   truth: "Flows documented (order, payment, promotions); Bruno collection in `api/bruno/
   is the de-facto API reference (417 requests)".
3. `apps/backend/README.md` lines 21–25, 37 — fix the Documentation section: keep links to
   `docs/modules.md`, `docs/flows`; **delete** links to `architecture.md`, `/docs/database`,
   `/docs/api`, `/docs/deployment`. When Phase 1 lands Scramble, `/docs/api` becomes real —
   the Phase 1 plan re-adds it.
4. `docs/README.md:8` — remove the `architecture.md` "(optional)" link.
5. `apps/backend/AGENTS.md:22` — delete or correct the Scramble line (agents must not trust
   a false claim).
6. `apps/storefront/lib/currency.ts` docstring — remove the "client components use the
   CurrencyProvider hook" sentence; describe the real mechanism (next-intl `price` number
   format registered in `i18n/request.ts`). Also note `fetchCurrency()` in
   `apps/storefront/lib/api/site.ts` is currently dead code — either wire it or leave a
   TODO; Phase 1 multi-currency work will consume it.
7. Sweep: `grep -rniE "scramble|architecture\.md|docs/api|docs/database|docs/deployment" README.md docs/ apps/*/README.md apps/*/AGENTS.md` → resolve every hit.

Suggested commit: `docs(monorepo): make readme and docs claims match reality`

## Task 0.3 — Rewrite module docs for the 19 real modules

1. `apps/backend/docs/modules.md` — replace the aspirational tree with the 19 real modules,
   one line each (purpose + 2–3 headline classes). Keep it a map, not a manual.
2. `apps/backend/docs/v1/modules.md` — add the 6 missing modules (Audit, Discount, Menu,
   Notification, Support, Tax) to the tree in the same table format, and add Contact to
   the tree (it has a body section already).
3. Cross-check against `ls apps/backend/Modules/`.

Suggested commit: `docs(backend): rewrite module docs for the 19 real modules`

## Task 0.4 — Record secrets audit + add CI guard

The audit is already done and clean (see Verified state). Make it durable:

1. Add a "Secrets" section to `SECURITY.md` (task 0.5): state what was audited, date,
   result, and the rule (env-only secrets; example files stay placeholder-only).
2. Add a gitleaks job to `.github/workflows/ci.yml` (6th parallel job):
   `gitleaks/gitleaks-action@v2` with the free entropy/config defaults. Confirm the test
   fixture DSN (`ErrorReportingTest.php:22`) does not trip it; add a `.gitleaksignore`
   entry if it does.

Suggested commit: `ci(infra): add gitleaks secrets scan to ci`

## Task 0.5 — Governance files + remove upstream template governance

Create at repo root:
1. `SECURITY.md` — how to report (GitHub private vulnerability reporting), supported
   versions (pre-release: main only), secrets section (from 0.4).
2. `CONTRIBUTING.md` — dev setup pointer (root `README.md` Quick start + docker compose),
   `sh scripts/install-hooks.sh`, commit convention link (`docs/COMMIT_CONVENTION.md`),
   test commands (`composer test` backend; builds for admin/storefront), i18n parity rule,
   where plans/roadmap live.
3. `CHANGELOG.md` — Keep a Changelog format, `## [Unreleased]` section seeded with the
   Phase 0/1 items; v0.1.0-alpha entry lands in Phase 2.
4. `.github/ISSUE_TEMPLATE/config.yml` (contact-links to Discussions) +
   `bug_report.yml` / `feature_request.yml` (YAML form syntax, not markdown) +
   `.github/PULL_REQUEST_TEMPLATE.md`.
5. `.github/FUNDING.yml` — create only if the owner wants sponsorship visible (ask once;
   skip silently otherwise).

Remove upstream files from `apps/admin/.github/` (root versions replace them; GitHub only
reads `.github/` at repo root, but leaving upstream files is misleading and
`apps/admin/.github/workflows/stale.yml` could confuse future tooling):
`CODE_OF_CONDUCT.md`, `CONTRIBUTING.md`, `FUNDING.yml`, `ISSUE_TEMPLATE/`,
`PULL_REQUEST_TEMPLATE.md`, `workflows/ci.yml`, `workflows/stale.yml`. Add a
root `CODE_OF_CONDUCT.md` (contributor-covenant v2, Baxela contact).

Suggested commits: `docs(monorepo): add security, contributing, changelog, and issue templates`
and `chore(admin): remove upstream shadcn-admin governance files`

## Task 0.6 — Repo packaging polish

1. `apps/admin/package.json` — `"name": "baxela-admin"`, keep `"version"`, keep
   `apps/admin/LICENSE` and upstream credit (0.1). Check nothing references the package
   name (grep `shadcn-admin` in `apps/admin/src` — expect only comments).
2. Delete `apps/admin/CHANGELOG.md` (upstream's release log — misleading inside Baxela);
   upstream history stays available via the Credits link.
3. Manual GitHub settings checklist (owner, 10 minutes, record completion here):
   - [ ] Description + homepage (demo storefront URL)
   - [ ] Topics: `php-ecommerce`, `laravel`, `ecommerce`, `nextjs`, `react`, `typescript`,
         `storefront`, `headless-commerce`, `rtl`, `open-source`
   - [ ] Social preview image 1200×640 (owner supplies; placeholder: Baxela wordmark on
         dark background)
   - [ ] Discussions enabled (Categories: Announcements, Q&A, Show and tell, Ideas)
   - [ ] Private vulnerability reporting enabled
   - [ ] Community standards page all-green

Suggested commit: `chore(admin): rename package to baxela-admin and drop upstream changelog`

---

## Definition of done

Execution status 2026-10-22: tasks 0.1–0.5 complete; 0.6 complete except the manual
GitHub settings (owner actions below). `FUNDING.yml` skipped (owner decision, per 0.5.5).
gitleaks pinned to `@v3.0.0` (v2 loses Node 20 runners). All changes committed and
pushed on 2026-10-22 (LICENSE: MIT).

- [x] `LICENSE` at root (MIT) + credits in admin README (credits already existed)
- [x] `grep` sweep from 0.2.7 returns no false claims
- [x] `modules.md` / `v1/modules.md` list 19 real modules
- [ ] gitleaks job green in CI (job added; verify on next push — if the Sentry test
      fixture DSN trips it, add a `.gitleaksignore` entry per task 0.4.2)
- [x] Root governance files exist; `apps/admin/.github/` has no upstream leftovers
- [ ] GitHub settings checklist ticked (manual — task 0.6.3, owner)
