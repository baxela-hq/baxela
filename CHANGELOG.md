# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html)
from its first stable release. Pre-alpha development happens on `main`.

## [Unreleased]

### Added

- **International SEO + structured data (gap-B2)**: every indexable
  storefront page emits canonical + six-locale hreflang alternates
  (`x-default` → default locale, mirroring the sitemap); the PDP gains
  per-product metadata with slug-form canonicals built from a new per-locale
  `slugs` map, and JSON-LD — `Product` for simple products,
  `ProductGroup`/`hasVariant` for variable ones (per-variant SKU + offer
  price), `BreadcrumbList` mirroring the visible breadcrumbs, `WebPage`,
  and store-settings-driven `Organization`/`WebSite` with stable `@id`s.
  Public product API adds purchase `availability` (in stock / out of stock,
  derived through the inventory gateway). Utility and account surfaces
  (auth, cart, checkout, profile, payment return) are `noindex`. Pricing in
  structured data uses the store default currency — deterministic for
  crawlers; display conversion never changes SEO output. Correctness over
  coverage: unavailable data is omitted, never fabricated.


- **Stack health endpoint (public)**: `GET /api/v1/core/public/health`
  probes database (critical → HTTP 503 on failure), Redis, and the queue
  (driver + pending/failed counts); non-critical failures degrade the
  payload but keep 200, `?strict=1` escalates any failure to 503. For
  uptime monitors; documented in the production README.

- **Per-driver refund capability (admin)**: drivers declare refunds via a
  `RefundableDriver` contract marker (Stripe, PayPal, Manual today); the admin
  methods/payments API surfaces `is_refundable`, the methods page badges it per
  driver, and "Issue refund" disables with an explanatory tooltip on gateways
  whose refund API isn't wired instead of failing after the click.
- **Multi-currency groundwork (backend)**: currencies gain admin-managed
  `exchange_rate` + `is_active`; new admin create/update endpoints (permissions derive
  from route names) and public `GET /core/v1/core/public/currencies` (default-first);
  `Money::convert()` for exact integer display conversion; orders snapshot
  `display_currency_code` + `display_fx_rate` at checkout when a non-base currency was
  selected (client supplies only the code; the rate is resolved server-side; charging
  stays in the base currency).
- **E2E testing**: Playwright in `apps/storefront` with a passing happy-path spec
  (signup → email-OTP via Mailpit → checkout with manual payment → order in profile)
  plus an `e2e` GitHub Actions workflow booting the full compose stack.
- **Security regression test**: every `api/v1/*/admin/*` route must carry
  `auth:sanctum` + permission middleware (`AdminRouteProtectionTest`).

- MIT `LICENSE` at the repo root (the admin panel keeps its upstream shadcn-admin MIT
  license and credits).
- Governance: `SECURITY.md`, `CONTRIBUTING.md`, `CODE_OF_CONDUCT.md`, issue templates,
  pull-request template.
- CI: gitleaks secrets scan on every push and pull request.

### Changed

- Truth pass over READMEs and `AGENTS.md`: removed the unimplemented Scramble API-docs
  claim and links to non-existent docs (`architecture.md`, `/docs/api`, `/docs/database`,
  `/docs/deployment`); the Bruno collection is now referenced as the de-facto API
  reference.
- `apps/backend/docs/modules.md` rewritten to describe the 19 real modules (the previous
  tree described an aspirational structure); `docs/v1/modules.md` tree now lists all 19
  modules and gained sections for Audit, Discount, Notification, Support, and Tax.
- Admin package renamed `shadcn-admin` → `baxela-admin`.

### Removed

- Upstream shadcn-admin governance files from `apps/admin/.github/` (FUNDING, stale
  issue-closer workflow, upstream templates) and its upstream `CHANGELOG.md`.
