# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html)
from its first stable release. Pre-alpha development happens on `main`.

## [Unreleased]

### Added

- **Gateway refund coverage (gap-B4)**: every gateway except NowPayments
  can now execute refunds (full or partial) from the admin, on top of the
  existing two-phase refund ledger. Adyen and Checkout.com accept
  asynchronously (`Idempotency-Key` / `Cko-Idempotency-Key`; `REFUND`,
  `REFUND_FAILED`, `REFUNDED_REVERSED`, `payment_refunded`,
  `payment_refund_declined` webhooks confirm); Mercado Pago decides in the
  response (`X-Idempotency-Key` mandatory since 2024-01) and reconciles
  edge cases via its payment notifications' `refunds[]`, matched per
  refund id; Razorpay refunds via the captured payment
  (`X-Refund-Idempotency` + the ledger uuid in the refund's `notes`,
  confirmed by `refund.processed` / `refund.failed` — enable refund
  events on the dashboard webhook). The ledger gained honest async
  semantics: a 2xx from an async gateway is an *acceptance*, not a
  completed refund — rows move to a new `processing` state (amount still
  reserved) until the definitive outcome arrives. Refund webhooks
  correlate via the ledger uuid sent as the gateway merchant reference
  and echoed back (fallback: the gateway refund id), after signature
  verification and validation of payment association, amount and
  currency; dashboard-issued refunds are acknowledged but never touch
  Baxela's accounting. A new terminal `reversed` state handles
  gateway-initiated reversals (Adyen `REFUNDED_REVERSED`, a late
  `REFUND_FAILED` on a finalized refund) in any webhook order — from a
  finalized refund the order's refunded amount is recomputed from the
  ledger's success-only rows and the payment status restored, atomically;
  from a pre-terminal row no accounting was ever applied.
  Refundable-balance reservations are never released by age: stale
  `processing` rows are resolved only by an operator who verified the
  provider's actual outcome. A gateway-side payment identifier (Adyen
  pspReference, Checkout.com/MP payment id, Razorpay `pay_` id) is
  persisted at webhook settle time as the refund target — payments
  settled before this release refuse gateway refunds explicitly. Admin
  surfaces label the new states, offer operator resolution for stale
  rows, and the payments page explains async confirmation; NowPayments
  states plainly on both admin surfaces that crypto payments cannot be
  reversed provider-side and reimbursements are the merchant's to
  arrange (i18n, 6 locales) — see `docs/plans/gap-b4-refund-drivers.md`.

- **Queue visibility (gap-B3)**: failed queued jobs are now operable.
  Core-module admin endpoints (`api/v1/core/admin/failed-jobs`) with
  separately-permissioned list/show/retry/delete (view never implies retry or
  delete) and an admin panel page (**Admin → Failed Jobs**) — server-side
  300-char exception excerpts in list rows, full exception + redacted payload
  in the detail view (the serialized job command is never displayed or
  unserialized; secret-ish payload keys are masked). Retry re-queues the job
  for another attempt with an explicit side-effects warning; delete is
  permanent. Every final job failure alerts the admin audience
  synchronously — best-effort, fully exception-isolated, database inbox
  primary, external channels (email/telegram/webpush) carry identifiers only.
  A scheduled queue-depth monitor (`core:queue-check-backlog`, every 5
  minutes with overlap protection) alerts on threshold crossings with
  cache-based deduplication and recovery clearing, and fails open if the
  cache is unavailable. Nightly `queue:prune-failed` retains 30 days
  (configurable via `core.queue.failed_jobs.retention_days`). SMTP delivery
  is now bounded (`MAIL_TIMEOUT`, default 15s) so a stalled relay cannot hang
  sync alert paths. Horizon is consciously deferred (Redis-only; re-addable
  later) — see `docs/plans/gap-b3-queue-visibility.md`.

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
