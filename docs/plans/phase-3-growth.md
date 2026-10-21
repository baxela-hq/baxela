# Phase 3 Plan — Compound to 1,000 Stars (Months 2–6)

> Execution plan for Phase 3 of `docs/ROADMAP.md`. Prerequisite: Phase 2 launched.
> This is a cadence phase, not a project with an end date — most items recur. Items 3.2
> Meilisearch and 3.5 multi-vendor are the two big engineering efforts; everything else is
> operations that must become routine.
>
> **Milestones:** ~100★ launch week → ~300★ month 2 → ~600★ month 4 → **1,000★ month 6–9**.
> One front-page HN day (~300–800★) compresses everything — that's why content (3.4)
> stays important even after launch.

## 3.1 Release cadence (recurring, every 2–3 weeks)

SOP per release:
1. Pick a theme a stranger can describe in one line ("Meilisearch", "6 more locales",
   "multi-vendor beta") — releases need a headline, not a changelog dump.
2. `CHANGELOG.md` entry → tag `v0.X.0` (semver-ish: alpha suffix drops at v0.6 when
   multi-vendor lands or e2e+baseline fully green, whichever first — rename that to the
   v1.0-beta discussion when close).
3. GitHub Release: notes + one GIF of the headline feature + migration notes if env vars
   or migrations changed.
4. Post the release to X + relevant Discussions thread; update comparison-table data
   points if competitors moved.
5. Regression gates before tagging: CI green (commit-lint, i18n parity, backend Pest,
   admin/storefront builds, phpstan, e2e), quickstart dry-run from clean clone
   (Phase 2 2.1.4) at least every other release.

Track: release date · headline · stars delta (GitHub traffic insights) → learn which
themes convert; double down.

## 3.2 Meilisearch (v0.2 or v0.3 headline)

Design sketch — env-gated so the core stays dependency-free:
1. `docker-compose.yml`: `search` profile service (getmeili/meilisearch, loopback port,
   MEILI_MASTER_KEY from env) mirroring how `mail`/`frontend` profiles are structured.
2. Backend: `composer require meilisearch/search-meilisearch-php` (client, not Scout —
   keep control of document shape). New `Modules/Catalog/app/Search/` (or Core
   `SearchIndexer`): document = variant/product fields the storefront filters on today
   (title per locale, category, brand, option values, price range, rating, active flags);
   `catalog:reindex` artisan command (bulk) + incremental hooks on product/variant
   save/delete via existing module events (Notification module's 42-listener wiring shows
   the event bus pattern).
3. Search endpoint: `GET v1/catalog/public/search` — Meilisearch-backed when
   `MEILISEARCH_HOST` is set, SQL-LIKE fallback otherwise (current behavior — the
   storefront keeps working without the service).
4. Storefront: header search dialog (`?q=` flow) now hits the search endpoint with
   locale; results page gains facets if cheap.
5. Tests: indexer unit tests against a faked client; endpoint falls back cleanly with
   MEILISEARCH_HOST empty; e2e adds a search step.
6. Content: this is a blog post + release GIF ("watch search go from LIKE to instant").
   Docs: `docs/THIRD_PARTY_SERVICES.md` search row flips to shipped.

## 3.3 Engineering content (3 posts, months 2–4)

Audience ranks these over feature news; HN/dev.to convert readers into stars:
1. **Idempotent checkout** — the `IdempotencyMiddleware` + DB-transaction orchestrator
   (`Modules/Cart/app/Actions/User/Cart/CheckoutAction.php`): why double-clicks and
   retries must create exactly one order; diagrams + failure-mode walkthrough.
2. **The order state machine** — dual fulfillment/payment tracks, guarded transitions
   (`apps/backend/docs/flows/order-state-machine.md` is the outline; add real incident
   scenarios: expired-order cancel command, refund flows).
3. **Ports and adapters across 19 Laravel modules** — the Core contracts layer (~60
   events, gateway interfaces), what it costs, what it buys (module autonomy, the
   multi-currency display work touching zero charging logic is the case study).

Each: dev.to cross-post + X thread + link in README docs landing.

## 3.4 Community locales (recurring)

1. `good-first-issue: locale` template: copy `en` message dirs (storefront 17 files,
   admin 46), translate, make `check-i18n-parity.mjs` green, PR. Mechanics are proven —
   Phase 1.4 did de/fr/es/ar.
2. Translator credit in the release notes of the version that ships their locale +
   CONTRIBUTING hall-of-fame table.
3. Backend `languages` table activation for each new locale (follow Phase 1.4.1);
   remember the DB-translation fallback prefers default language since Phase 1.4.2.

## 3.5 Multi-vendor scope decision (~v0.3–v0.4)

Before writing code, write `docs/proposals/multi-vendor.md` answering:
1. Seller identity: `sellers` table + role vs separate users (Baxela has spatie
   permissions + `User` module — role is cheaper, separate seller entity is cleaner for
   commissions/payouts; recommend entity).
2. Product ownership: products gain `seller_id` (nullable = platform-owned) — migration
   blast radius across catalog/cart/order.
3. Approval flow: direct-list vs admin-approval default (recommend approval).
4. Commission: per-seller percent on order lines (order items gain `seller_id` +
   `commission_minor` snapshot — follows the Phase 1.3 snapshot pattern).
5. Payout tracking: ledger table vs export-only for v1 (recommend export-only).
6. Admin: seller CRUD + product-approval queue; Storefront: seller profile pages.
7. Sequencing: backend contracts first (`SellerGatewayInterface` in Core contracts —
   same pattern as Order/Payment gateways), then admin, then storefront.
Decision doc merged = the feature's plan; don't start schema work before it's merged.

## 3.6 Showcase + tracking (recurring)

1. `docs/showcase.md` + README section: real stores on Baxela (offer early adopters a
   feature in exchange for a listing; one real store persuades more than any table).
2. Monthly ritual (calendar reminder): star-history screenshot → referrer review →
   milestone check (300/600/1000) → adjust next release theme + content topics → note
   results at the bottom of this file.

## Definition of done (rolling)

- [ ] First post-launch release tagged with a headline theme
- [ ] Meilisearch shipped or scheduled with a dated decision
- [ ] 3 engineering posts published
- [ ] ≥3 community locales merged from outside contributors
- [ ] Multi-vendor proposal doc merged (decision, not necessarily shipped)
- [ ] ≥1 real store in the showcase
- [ ] 1,000★ — then set the next goal: contributors, not stars (v1.0, plugin story, or
      the marketplace question Bagisto proved matters)
