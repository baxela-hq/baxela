# Extension System & Marketplace — Roadmap

> **Purpose of this document:** Self-contained plan for Baxela's extension/plugin system
> and its marketplace. Captures all decisions and build order from the planning discussion —
> readable later without conversation history, or handable to an agent session.
>
> **Created:** 2026-11-01 · **Owner:** solo maintainer · **Status:** planning complete, not started
>
> **How to use:** Work phases strictly in order (0 → 6). Phase 0 is the hard prerequisite —
> nothing else works without it. The marketplace is deliberately last: it is worthless until
> the extension system is proven side-loaded. Tick checkboxes and note dates as tasks complete.

---

## 1. Strategic decisions (the "why")

| # | Decision | Reason |
|---|----------|--------|
| 1 | **Core platform stays geography-neutral.** No country branding, no Iranian services in core, international defaults (currency/locale/calendar). | Visible Iran-tied payment code in core would deter Western (esp. US) merchants for sanctions-compliance reasons, fairly or not. Neutral core + regional gateway extensions serves both markets. |
| 2 | **Zarinpal lives outside core** — its own repo, published as a Composer package, installed as an extension. | Same pattern as WooCommerce/Shopify gateways: invisible to anyone who doesn't install it. Also protects the owner formally. |
| 3 | **Extensions are Composer packages.** `baxela:install` is a wrapper around `composer require` + lifecycle hooks. | Composer already solves versioning, deps, autoload, lock files. Never build custom download/verify machinery. |
| 4 | **The marketplace is the only *supported* channel.** Side-loading via raw composer works (it's open source) but is unsupported. | Can't technically prevent it, and shouldn't — private in-house extensions are legitimate. Curation is the trust layer, not a wall. |
| 5 | **Marketplace is an independent repo** (`baxela-marketplace`), a plain Laravel app — *not* a modular monolith, *not* inside this monorepo. | Owner plans numerous additional apps; independent repo per app. Marketplace is Package/Release/Vendor CRUD + file serving — module infra would be overkill. |
| 6 | **Extension updates are deliberate, never auto.** `composer.lock` pins versions; update = explicit `baxela:update`. | Silent self-updating payment code on live stores is unacceptable. |
| 7 | **Compatibility is declared per release** (`compat.core` constraint in the manifest) and enforced at install/update, at the marketplace, and at runtime (fail closed → disabled, never fatal). | The core-2.0-vs-old-extension scenario must never brick a store. |

## 2. Architecture at a glance

```
┌─ This monorepo (baxela) ──────────────┐   ┌─ baxela/zarinpal (repo #2) ──┐
│  apps/backend:                        │   │  Composer package            │
│   - baxela:install/remove/update/     │   │  type: baxela-extension      │
│     list/enable/disable commands      │◄──┤  PaymentDriverInterface impl │
│   - extensions table, compat guard    │   │  webhook route, config       │
│   - DB-driven payment methods         │   └──────────────┬───────────────┘
│  apps/admin: Extensions settings page │                  │ git tag push
└───────────────────────────────────────┘                  ▼
                                           ┌─ baxela-marketplace (repo #3) ─┐
                                           │  Laravel app:                  │
                                           │  - Composer registry           │
                                           │    (packages.json + dist zips) │
                                           │  - Catalog API (search/updates)│
                                           │  - Sync command + GH webhook   │
                                           │  - Public frontend: browse,    │
                                           │    submit (GitHub OAuth)       │
                                           │  - Review back office          │
                                           └────────────────────────────────┘
```

Install flow: admin panel / `baxela:search` → catalog API → `baxela:install vendor/baxela-slug` → `composer require` (resolves against marketplace registry) → migrations → publish config → `install()` hook → row in `extensions` table.

## 3. Package contract (rules enforced at submission AND install)

- Composer name: `vendor/baxela-*` (regex `^[a-z0-9][a-z0-9_.-]*/baxela-[a-z0-9][a-z0-9_.-]*$`). Vendor `baxela` reserved for first-party.
- `"type": "baxela-extension"`.
- `extra.baxela` manifest: human name, slug, extension type (`payment-driver`, `shipping-carrier`, …), icon, `compat.core` (e.g. `"^1.0"`).
- Required: license field, README, at least one semver git tag.
- Provider extends a **base `Baxela\Extension` class shipped by core** (centralized compat guard — evolve once, applies to all).

```json
"extra": {
    "laravel": { "providers": ["Baxela\\Zarinpal\\ZarinpalServiceProvider"] },
    "baxela": {
        "name": "Zarinpal Gateway", "slug": "zarinpal",
        "type": "payment-driver",
        "compat": { "core": "^1.0" }
    }
}
```

Integration contract: the provider merges itself into registries at boot, e.g. `config(['payment.drivers.zarinpal' => ZarinpalPaymentDriver::class])`. No core changes per extension — ever.

## 4. Build phases

### Phase 0 — Core prerequisite: DB-driven payment methods

The hard blocker. `PaymentMethodEnum` is closed and threaded through actions, requests,
models, seeders, and `PaymentDriverManager` — no external payment extension can exist while
methods are enum cases.

- [ ] Make the `payment_methods` table the source of truth for which methods exist (name, slug, driver, enabled).
- [ ] Drop `PaymentMethodEnum` from public surfaces; key everything off the string driver name (`PaymentDriverManager::forName()` already exists).
- [ ] Seed the 8 built-ins (manual, stripe, paypal, adyen, nowpayments, mercadopago, checkoutcom, razorpay).
- [ ] `isConfigured()` logic becomes data-driven / per-driver contract instead of a hardcoded `match`.

### Phase 1 — Extension system (in this monorepo, `apps/backend`)

- [ ] `extensions` table migration: slug, version, enabled, settings JSON, installed_at.
- [ ] Base `Baxela\Extension` ServiceProvider class: metadata accessors, `install()/uninstall()` hooks, boot-time compat check (`Semver::satisfies` vs `Baxela::VERSION`) → no-op + warning when incompatible. **Fail closed, never fatal.**
- [ ] Core version constant (`Baxela::VERSION`), maintained per release.
- [ ] Commands: `baxela:install` (validate slug → `composer require` via `Process` → migrate → `vendor:publish` → install hook → DB row), `baxela:remove` (disable → uninstall hook → `composer remove`), `baxela:list`, `baxela:enable/disable`, `baxela:update` (composer update → migrate → refresh row → caches). `--ignore-compat` escape hatch documented.
- [ ] Disabled extension = inert: registered driver keys removed, payment method hidden, webhook routes return clean "method disabled" response.
- [ ] Admin-facing API endpoints for the extensions page (list, enable, disable).

### Phase 2 — Zarinpal extension (repo #2: `baxela/zarinpal`)

- [ ] Scaffold package: `composer.json` (type + manifest), `src/ZarinpalServiceProvider.php` (extends `Baxela\Extension`), `config/zarinpal.php`, webhook route under `api/v1/payment/webhook/zarinpal` (signature-verified, no auth), lang errors, tests.
- [ ] `ZarinpalPaymentDriver` implements `Modules\Core\Contracts\Gateways\Payment\PaymentDriverInterface`.
- [ ] Dev-mount via Composer `path` repository while both repos are local; verify full flow side-loaded: install → sandbox payment → webhook → refund.
- [ ] **End-to-end proof without any marketplace.** Do not start Phase 3 before this passes.

### Phase 3 — Marketplace app (repo #3: `baxela-marketplace`)

Plain Laravel app. Three jobs only, in this order:

- [ ] Composer registry: `packages.json` route + dist zip serving, generated from DB. App skeleton in the monorepo ships `"repositories": [{ "type": "composer", "url": "https://repo.baxela…/…" }]`.
- [ ] Ingestion: `marketplace:sync vendor/repo` (fetch tags + composer.json via GitHub API → releases + zips) behind a `SourceProvider` interface (GitHub-only v1; GitLab later).
- [ ] Catalog API: search/list endpoint + `POST /api/packages/updates` (input: core version + installed slugs/versions → compatible updates only).
- [ ] **Reserve the auth pathway now** (token-filtered `packages.json` middleware) even though v1 is anonymous read — this is how paid/licensed extensions will gate installs later.
- [ ] Back office: minimal internal panel (Filament is fine) — approve/reject, releases, stats.

### Phase 4 — Marketplace frontend + submissions

Public face of the marketplace app; linked from the home site nav, not embedded.

- [ ] Pages: browse/search (filter by extension type, sort by downloads), package detail (README, versions, install command, compat badge).
- [ ] GitHub OAuth sign-in; submit = paste repo URL; ownership proven via push-access check on the repo.
- [ ] Automated validation pipeline (instant, self-served rejections): naming regex, manifest completeness, license + README, semver tag, secret scanning, red-flag greps.
- [ ] Submission statuses: `pending → approved | rejected` (with written reason) at repo level; `flagged` (community report button), `orphaned` (repo gone), `yanked` (release pulled) operational states.
- [ ] After approval: GitHub webhook installed on the repo → new tags auto-ingest, no re-review per release. Major version bumps / manifest changes re-flag.
- [ ] Manual review checklist (markdown file in the marketplace repo): install cleanly in a disposable Baxela container → happy path for its type (sandbox payment + webhook for drivers) → read the webhook handler (signature verification actually enforced?) → manifest honesty.
- [ ] Later optimizations (don't build now): automated install smoke-test in CI, core-N readiness dashboard for authors.

### Phase 5 — Admin panel integration (`apps/admin`)

- [ ] Extensions settings page: installed list, enable/disable toggles, per-driver credential forms.
- [ ] Move payment driver credentials from `.env` to the Setting module (DB) — required for a hosted product.
- [ ] "Update available" badge via the updates endpoint; changelog link. Notification only — merchant pulls.

### Phase 6 — Ecosystem maturity (only when there are real third-party extensions)

- [ ] Extract `Modules\Core\Contracts` into a real `baxela/contracts` Composer package → Composer natively enforces compat, replaces the custom checker. Also the future home of the base `Extension` class and shared schema traits.
- [ ] Paid extensions / licensing (license key as Composer auth against the registry).
- [ ] Core-N readiness dashboard for authors; automated test harness (extension × core matrix in CI).
- [ ] GitLab source support; abandoned-package adoption flow.

## 5. Policies that come with this system (write these down when shipping Phase 1)

1. **Core semver promise:** within a major, the extension-facing surface (gateway contracts, events, schemas) is additive-only. Breaking changes = major bump. The compat system is arithmetic; this discipline is the actual guarantee.
2. **End of pre-v1 migration rewriting** the day a third-party extension is listed. Core schema changes become additive migrations only — schema is a promise.
3. **Extensions update up, never down.** Migrations don't roll back; same as every Composer package. Document once, don't fight it.
4. **Compatibility has three layers:** install/update check (hard stop + `--ignore-compat`), marketplace filtering (incompatible releases never offered), runtime guard (incompatible = disabled, never fatal). Core-upgrade pre-flight reports per-extension: compatible version exists / none exists (will be disabled) / update after upgrade.
5. **First-party-only while pre-v1 migration rewriting still applies** — Zarinpal and core are updated together until the additive-migrations policy kicks in.

## 6. Related docs

- `docs/ROADMAP.md` — public-launch roadmap (separate initiative; launch phases come first — this doc's phases are blocked until launch work frees capacity, except Phase 0–1 which can start anytime).
- `apps/backend/AGENTS.md` — module conventions the extension system must respect (schemas, actions, webhook conventions).
