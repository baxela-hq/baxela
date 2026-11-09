# Extension System & Marketplace — Roadmap

> **Purpose of this document:** Self-contained plan for Baxela's extension/plugin system
> and its marketplace. Captures all decisions and build order from the planning discussion —
> readable later without conversation history, or handable to an agent session.
>
> **Created:** 2026-11-01 · **Revised:** 2026-11-08 — Phase 2 is now a throwaway-proof sample
> extension; Zarinpal deferred to Phase 6 · **Owner:** solo maintainer · **Status:** Phases 0–1
> done (2026-11-09/10); Phase 2 next, then the marketplace phases
>
> **How to use:** Work phases strictly in order (0 → 7). Phase 0 is the hard prerequisite —
> nothing else works without it. The marketplace waits until the extension system is proven
> side-loaded by the Phase 2 sample. Tick checkboxes and note dates as tasks complete.

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
┌─ This monorepo (baxela) ──────────────┐   ┌─ Extension repos (#2 onward) ─┐
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

- [x] Make the `payment_methods` table the source of truth for which methods exist (name, slug, driver, enabled). *(done 2026-11-09 — E.0)*
- [x] Drop `PaymentMethodEnum` from public surfaces; key everything off the string driver name (`PaymentDriverManager::forName()` already exists). *(done 2026-11-09 — enum deleted; manager string-first; validation accepts registry ∪ stored rows so unknown → 422, registered-no-row → `method_inactive`, stale-row-unregistered → `method_not_supported`)*
- [x] Seed the 8 built-ins (manual, stripe, paypal, adyen, nowpayments, mercadopago, checkoutcom, razorpay). *(done 2026-11-09 — manual+stripe seed active as before; the rest appear as disabled rows via the admin list's lazy creation over `config('payment.drivers')` — same mechanism, now registry-driven)*
- [x] `isConfigured()` logic becomes capability-driven instead of a hardcoded `match` — follow the pattern the refund drivers landed on 2026-11-08 (`supportsRefund()` checks `instanceof RefundableDriver`): e.g. a per-driver credential declaration, so built-ins and extensions declare their own config keys. *(done 2026-11-09 — `ConfigurableDriver::credentialKeys()` contract in Core; all 8 drivers declare lifecycle-complete keys incl. the webhook-verification credentials the old match missed on stripe/paypal/adyen; unregistered/undeclaring drivers fail closed)*

### Phase 1 — Extension system (in this monorepo, `apps/backend`)

- [x] `extensions` table migration: slug, enabled, timestamps — the only state not derivable from Composer (version is read live; settings go through the Setting module in Phase 5). *(done 2026-11-10 — `core_extensions`, E.1)*
- [x] Base `Baxela\Extension` ServiceProvider class: metadata accessors, `install()/uninstall()` hooks, boot-time compat check (`Semver::satisfies` vs `Baxela::VERSION`) → no-op + warning when incompatible. **Fail closed, never fatal.** *(done 2026-11-10 — `Modules\Core\Extensions\Extension`, final `boot()` with the three guards in order: missing row → disabled → compat)*
- [x] Core version constant (`Baxela::VERSION`), maintained per release. *(done 2026-11-10 — `app/Baxela.php`, starts at `0.1.0`)*
- [x] Commands: `baxela:install` (validate slug → `composer require` via `Process` → migrate → `vendor:publish` → install hook → DB row), `baxela:remove` (disable → uninstall hook → `composer remove`), `baxela:list`, `baxela:enable/disable`, `baxela:update` (composer update → migrate → refresh row → caches). `--ignore-compat` escape hatch documented. *(done 2026-11-10 — six commands; version never stored so update has no row to refresh; `--ignore-compat` bypasses only the install hard stop, the runtime guard re-evaluates live)*
- [x] Disabled extension = inert: registered driver keys removed, payment method hidden, webhook routes return clean "method disabled" response. *(done 2026-11-10 — a skipped boot never merges the driver; the webhook answers `payment.process.method_not_supported`, proven in `ExtensionBootGuardTest`)*
- [x] Admin-facing API endpoints for the extensions page (list, enable, disable). *(list done 2026-11-10 — `GET api/v1/core/admin/extensions` with live version + skip reason; write endpoints stay CLI-only until Phase 5 per the E.0–E.2 plan)*

### Phase 2 — Sample extension: prove the system (repo #2: `baxela/baxela-sandbox-gateway`)

Deliberately **not** Zarinpal — a minimal fake payment gateway ("Sandbox Gateway") with zero
external API calls. Isolating extension-system bugs from payment-provider bugs is the whole
point: if the flow breaks here, the cause is the system. It also permanently earns its keep
three ways: a dev/testing gateway for checkout flows without real credentials, the reference
implementation for marketplace authors, and the first package the marketplace ingests (Phase 3).

- [x] Scaffold package: `composer.json` (type + manifest with `compat.core`), `src/SandboxServiceProvider.php` (extends `Baxela\Extension`), `config/sandbox.php`, lang errors, tests. **No webhook route registration needed** — verified 2026-11-08: the parameterized `POST api/v1/payment/webhook/{driver}` route already resolves any registered driver; the driver just implements `handleWebhook()`. *(done 2026-11-10 — local git repo at `apps/backend/extensions/baxela-sandbox-gateway`, tags v0.0.1/v0.0.2; manifest slug = the composer package name, the checkout driver key 'sandbox' is separate; package tests run from the host backend binary per its README)*
- [x] `SandboxPaymentDriver` implements `PaymentDriverInterface` + `RefundableDriver` (refund is a local no-op that settles the refund record), registers itself via `config(['payment.drivers.sandbox' => SandboxPaymentDriver::class])`. *(done 2026-11-10 — also `ConfigurableDriver` with `['webhook_secret']`; real HMAC-SHA256 over the raw body with constant-time compare; env-backed config merged in `extensionBoot()` so a disabled extension leaves nothing in the payment namespace)*
- [x] Dev-mount via Composer `path` repository while repos are local. *(done 2026-11-10 — committable `repositories` entry + gitignored `extensions/`; package declares an explicit `version` field because composer's stability gate does not guess from git tags on path repos)*
- [x] **System-proof checklist — all must pass before Phase 3:** *(all green 2026-11-10, run live against the dev compose stack; the proof found and fixed three real system bugs — see the plan doc §5 execution notes)*
  - [x] `baxela:install` from the local path repo → row in `extensions`, shows in `baxela:list`, sandbox method appears in checkout
  - [x] checkout completes with the sandbox driver; its webhook endpoint fires and settles an order *(unsigned + replay also verified: 400 invalid, idempotent ack)*
  - [x] `baxela:disable` / `baxela:enable` flips it at runtime; core untouched *(fresh-boot checks: method gone from storefront, webhook `method_not_supported`, skip reason `disabled`)*
  - [x] tag `v0.0.2` with a trivial change → `baxela:update` applies cleanly, version row refreshes *(read live from composer — no row to refresh)*
  - [x] compat test: declare `compat.core` above the current core version → install blocked with a clear error; runtime guard no-ops a force-installed copy *(skip reason names the required range)*
  - [x] `baxela:remove` unregisters everything cleanly *(row, method row via uninstall hook, vendor package, driver)*
- [x] **End-to-end proof without any marketplace.** Do not start Phase 3 before this passes. *(passed 2026-11-10; steady state is mountable-not-installed — install is one command, and an always-installed dev extension would change registry-derived test expectations per machine)*

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
- [ ] Move payment driver credentials from `.env` to the Setting module (DB) — required for a hosted product. Reuse the secrets-encrypted-at-rest pattern from the Webhooks module (landed with the C.2 outbox work, 2026-11-08).
- [ ] "Update available" badge via the updates endpoint; changelog link. Notification only — merchant pulls.

### Phase 6 — First production extension: Zarinpal (repo #4: `baxela/baxela-zarinpal`)

Timing is flexible relative to Phases 3–5 — the build itself can start once Phase 2 passes;
the marketplace (Phase 3) is only needed for the publish step at the end.

- [ ] Same scaffold as the sample; `ZarinpalPaymentDriver` implements `PaymentDriverInterface` + `RefundableDriver` (Zarinpal's API supports refunds).
- [ ] `config/zarinpal.php`: merchant ID, sandbox flag, callback/return URLs; `handleWebhook()` implementation — served by the existing parameterized `api/v1/payment/webhook/{driver}` route, signature-verified in the driver.
- [ ] Publish through the marketplace as its first real third-party-shaped package — exercising submission validation, ingestion, and update-check against it.

### Phase 7 — Ecosystem maturity (only when there are real third-party extensions)

- [ ] Extract `Modules\Core\Contracts` into a real `baxela/contracts` Composer package → Composer natively enforces compat, replaces the custom checker. Also the future home of the base `Extension` class and shared schema traits.
- [ ] Paid extensions / licensing (license key as Composer auth against the registry).
- [ ] Core-N readiness dashboard for authors; automated test harness (extension × core matrix in CI).
- [ ] GitLab source support; abandoned-package adoption flow.

## 5. Policies that come with this system (write these down when shipping Phase 1)

1. **Core semver promise:** within a major, the extension-facing surface (gateway contracts, events, schemas) is additive-only. Breaking changes = major bump. The compat system is arithmetic; this discipline is the actual guarantee.
2. **End of pre-v1 migration rewriting** the day a third-party extension is listed. Core schema changes become additive migrations only — schema is a promise.
3. **Extensions update up, never down.** Migrations don't roll back; same as every Composer package. Document once, don't fight it.
4. **Compatibility has three layers:** install/update check (hard stop + `--ignore-compat`), marketplace filtering (incompatible releases never offered), runtime guard (incompatible = disabled, never fatal). Core-upgrade pre-flight reports per-extension: compatible version exists / none exists (will be disabled) / update after upgrade.
5. **First-party-only while pre-v1 migration rewriting still applies** — first-party extensions (the sandbox sample, later Zarinpal) and core are updated together until the additive-migrations policy kicks in.

## 6. Related docs

- `docs/plans/extension-e0-e2.md` — **detailed execution plan for Phases 0–2** (prefer it over §4's outline; verified state, design decisions, edge cases, acceptance tests).
- `docs/ROADMAP.md` — public-launch roadmap (separate initiative; launch phases come first — this doc's phases are blocked until launch work frees capacity, except Phase 0–1 which can start anytime).
- `docs/PLATFORM_GAPS_ROADMAP.md` — gap roadmap between launch and the extension system (phases lettered A–D to avoid collision with the numbered phases here).
- `apps/backend/AGENTS.md` — module conventions the extension system must respect (schemas, actions, webhook conventions).
