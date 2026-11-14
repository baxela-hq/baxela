# Plan marketplace-P5 — Admin panel integration (extensions page, credentials, update badge)

> **Purpose of this document:** Execution plan for Phase 5 of
> `docs/EXTENSION_MARKETPLACE_ROADMAP.md`. Phase 3/4 built the marketplace as a product
> (registry, ingestion, catalog, updates endpoint, submissions); Phase 5 is the consumer
> side **inside the Baxela monorepo** — the admin surfaces merchants use day-to-day.
> The primer stands alone; verified facts are §1. Design decisions D1–D10; build order §6.
>
> **Created:** 2026-11-14 · **Owner:** solo maintainer · **Status:** draft — awaiting review
>
> **How to use:** Slices strictly in order (write endpoints → credential store → updates
> proxy → admin UI → hardening). Re-verify ⚠-marked facts before relying on them.
> Conventional commits; never commit unless asked. Heavy commands `nice -n 10`.

## 1. Verified state (2026-11-14)

| # | Fact | Evidence |
|---|---|---|
| V1 | `core_extensions` table: `slug` (unique), `enabled` (bool, indexed); `ExtensionManager` guards boot fail-closed in order missing_row → disabled → compat; per-request static `$state` cache, `toggle()` + `flush()` exist | `Modules/Core/database/migrations/2026_11_10_000001_*`, `Modules/Core/app/Extensions/ExtensionManager.php:40-67,136-145` |
| V2 | The only extensions HTTP surface is read-only `GET api/v1/core/admin/extensions` (returns `slug, enabled, installed, version, skip_reason`; permission `core.admin.extensions.list`). **No write endpoints exist** — enable/disable is CLI-only, deliberately deferred to this phase | `Modules/Core/routes/api/admin.php:48-50`, `ListExtensionsAction.php:26-58` |
| V3 | Permissions auto-derive from route names (`PermissionMiddleware` strips `api.`); `SyncPermissionsAction` `firstOrCreate`s rows for every `api.*.admin.*` route — a new route yields its permission on the next sync, no catalog edits | `Modules/Core/app/Http/Middleware/PermissionMiddleware.php:27-35`, `Modules/Auth/app/Actions/SyncPermissionsAction.php` |
| V4 | `register()` on the base `Extension` is **final and empty by design** — a disabled extension's `extensionBoot()` never runs, so its config (incl. payment-driver merges) is never present in a booting process | `Modules/Core/app/Extensions/Extension.php:14-17,49-60` |
| V5 | Payment driver credentials are **env-only today**: `config('payment.<driver>.*')` backed by `env()` in `Modules/Payment/config/payment.php`; `isConfigured()` checks truthiness of every `credentialKeys()` entry; no DB store exists | `Modules/Payment/config/payment.php:28-35`, `PaymentDriverManager.php:52-77`, `ConfigurableDriver.php` |
| V6 | The Setting module has an encrypted-secret precedent: `FX_API_KEY` stored with manual `Crypt::encryptString`, masked (`'********'`) in the admin resource, masked value rejected on submit, `null` clears / omission keeps; the reader returns a **key-state** (`setting\|env\|none\|undecryptable`), decrypt on read with env fallback | `UpdateSettingAction.php:49-57`, `ResolvesFxSettings.php:94-111`, `SettingResource.php:30-45` |
| V7 | The Webhooks pattern the roadmap points at is a **dedicated table + Laravel built-in `'encrypted'` cast**, secret omitted from the resource (show-once via create/regenerate) | `WebhookEndpoint.php:34`, `WebhookEndpointResource.php:18-31` |
| V8 | The updates endpoint `POST /api/packages/updates` (public, **unthrottled**, no token) takes `{"core": "<semver>", "installed": {"<slug>": "<semver>"}}` and returns `{"data": {"<slug>": {"state", "latest", "update_available", "current_yanked"}}}` with the six-state contract | `baxela-marketplace/app/Http/Controllers/UpdatesController.php:23-57`, `UpdateResolver.php:44-94` |
| V9 | The marketplace app ships **no `config/cors.php`** → `HandleCors` short-circuits and **zero `Access-Control-Allow-Origin` headers are emitted**; a browser SPA cannot call it cross-origin | `baxela-marketplace/config/` (no cors.php), `HandleCors::hasMatchingPath()` |
| V10 | `Baxela::VERSION = '0.1.0'`; no outbound marketplace integration exists in the backend; `composer/semver ^3.6` already required; composer repositories = single `path` entry for `extensions/*` | `app/Baxela.php:13`, `apps/backend/composer.json:11-20` |
| V11 | **Octane caveat:** the app now runs RoadRunner (`config/octane.php`, `app/Octane/FlushExtensionState.php` flushes `ExtensionManager` state per request, but **service providers boot once per worker**) — an enable/disable via HTTP flips the row for the *next* worker; already-booted workers keep the extension's registered bindings (incl. merged payment drivers) until `octane:reload` | `config/octane.php:84`, `app/Octane/FlushExtensionState.php:12-15` |
| V12 | Admin settings page pattern: conditional tabs driven by settings-row presence; CRUD tabs (currency, languages) mount **outside** the settings `<form>`; i18n in `public/locales/<lang>/setting/setting.json`; invalidation keys `['settings']` etc. | `apps/admin/src/features/setting/settings/index.tsx:123-139,584-589` |

## 2. Scope

**In:** extension enable/disable HTTP endpoints; a DB-backed, encrypted-at-rest credential
store for payment drivers with env fallback; a backend proxy for the marketplace updates
endpoint with cache + honest degraded states; one admin "Extensions" tab (list, toggles,
credential forms, update badges); marketplace-side throttling of the public updates
endpoint; Bruno collection updates.

**Out (per the roadmap):** auto-updates of any kind (badge is notification-only — the
merchant pulls); paid extensions/licensing (Phase 7); GitLab sources; GitLab-source
support; any storefront surface; any new marketplace feature beyond the throttle.

## 3. Design decisions

| # | Decision | Why |
|---|---|---|
| **D1** | **Backend proxy for updates** — new `GET api/v1/core/admin/extensions/updates`. The backend collects installed extension slugs + live versions (`ComposerPackages::extensionSlugs()` + `InstalledVersions`), sends `{"core": Baxela::VERSION, "installed": {...}}` to the marketplace, and passes the `data` payload through inside its own envelope. | V9: the marketplace emits no CORS headers — a browser call is impossible without marketplace changes; V8: the endpoint is public **and unthrottled** — a server-side proxy is the single place to cache, timeout, and permission-gate; keeps the marketplace URL/token out of the browser. |
| **D2** | **Marketplace URL is a config seam, default-off.** `config('core.extensions.marketplace_url')` backed by `env('MARKETPLACE_URL', '')`. Empty → the updates feature reports `marketplace_unconfigured` and the UI hides the badge. Same policy as the registry URL that stayed out of `composer.json` until deployment (roadmap M.1). | Fail-closed house style; a dev machine without the marketplace deployed must not show broken UI. |
| **D3** | **Proxy caches + degrades honestly.** `Cache::remember('core.extension-updates', 15 min, ...)` keyed additionally by a hash of the installed map; cache forgotten on extension toggle/install/remove and on credential-store changes (cheap: one key). Marketplace unreachable/timeout (≥ 5 s) → HTTP 200 with `"availability": "marketplace_unreachable"` and the last cached payload served stale if present (stale flag `cached: true`). | Protects an unthrottled public endpoint from N admin panels polling; the badge is advisory — degraded states must never read as errors. |
| **D4** | **Write endpoints stay thin:** `POST api/v1/core/admin/extensions/{slug}/enable` and `.../disable` → validate slug against `ExtensionSchema::SLUG_PATTERN` + must be an installed extension package (`ComposerPackages::isExtensionPackage`) → `ExtensionManager::toggle()` → `flush()` → return `ExtensionResource`. Unknown/not-installed → `core.extensions.not_installed` (422); not an extension type → same code. | Reuses V1/V2 machinery verbatim; permissions `core.admin.extensions.{enable,disable}` auto-derive (V3) — zero permission plumbing. CLI and HTTP converge on the same `toggle()`. |
| **D5** | **Credential store: dedicated table in the Payment module**, *not* a Setting row. `payment_driver_credentials` (`driver` string, `key` string, `value` text with built-in `'encrypted'` cast, unique `(driver, key)`). Read side never exposes values: `GET api/v1/payment/admin/credentials` returns per-driver **key-states** (`setting\|env\|none\|undecryptable`) exactly like the FX resolver (V6); write side `PUT api/v1/payment/admin/credentials/{driver}` accepts `{key: value}` map (values write-only), delete via `DELETE .../{driver}/{key}`. | The roadmap says "Reuse the secrets-encrypted-at-rest pattern from the **Webhooks module**" — that pattern *is* a dedicated encrypted-cast table (V7), not a Setting row; per-credential rows give clean masking semantics, no Setting DB-enum churn, no mixed-type `value` column hacks. "Setting module (DB)" is honored as "DB-backed, admin-managed" — deviation noted for the roadmap tick. |
| **D6** | **Resolution order: DB credential → env config → none**, surfaced as the key-state contract above. `ResolvesPaymentCredentials` lives in `Modules/Payment/app/Support/` with a Core contract (`PaymentCredentialResolverInterface`) like `PricingModeResolverInterface` (V6); `isConfigured()` switches to the resolver (DB present → configured even with no env). Plaintext is decrypted **per call, never cached persistently**; `undecryptable` after APP_KEY rotation is a distinct state the UI renders as an action item (re-enter), matching the FX precedent. | One seam (the contract docblock already promises "a tenant-aware credential resolver can replace the lookup later without touching a single driver" — this is that moment). Drivers keep declaring `credentialKeys()` and stay untouched. |
| **D7** | **Octane worker staleness is surfaced, not hidden.** The toggle response and the UI note state: under Octane, the change is guaranteed for newly booted workers; already-booted workers keep current bindings until `octane:reload`/restart (V11). Under FPM (prod compose today) every request re-boots providers — immediate. No automatic `octane:reload` from the endpoint (racy, affects all workers mid-flight). | Honest-state policy; V11 makes silent-immediacy a lie. Documented in the tab's help text and the roadmap tick. |
| **D8** | **Admin UI: one unconditional "Extensions" tab** in the settings page, mounted outside the settings form (currency/languages CRUD pattern, V12). Three sections top-to-bottom: (1) installed extensions table — slug, version, status badge (`enabled` / `skip_reason` translated), enable/disable row actions with confirm; (2) driver credentials — per ConfigurableDriver block generated from `credentialKeys()`, masked inputs, per-key state chip (`saved` / `env` / `missing` / `needs re-entry`); (3) updates — per-extension badge (`update available → x.y.z` / `up to date` / honest states incl. yanked), changelog link to the marketplace package page, availability banner when `marketplace_unconfigured`/`unreachable`. | V12 gives the tab pattern for free; extensions management is CRUD-like, so it must not live inside the settings form. One page for all three concerns matches the roadmap's single "Extensions settings page" item. |
| **D9** | **No contract changes to drivers.** `credentialKeys()` already declares everything the UI and `isConfigured()` need (V5). Labels for credential inputs come from admin i18n (`payment/credentials.json` keyed `driver.key`), not a new contract method — the 8 built-in drivers stay byte-identical. | Smallest blast radius; extension authors add zero code to become credential-form-ready. |
| **D10** | **Marketplace-side throttle** (only expected marketplace change): `throttle:60,1` per IP on the public `api/packages` + `api/packages/updates` routes in `baxela-marketplace/bootstrap/app.php`, with a test. | V8: the endpoint is public and unthrottled — Phase 5 makes it load-bearing for every admin panel; the cheapest hardening is a limiter at the source. |

## 4. Edge cases

- Toggle a slug that exists in `core_extensions` but is not composer-installed → 422
  `not_installed` (row stays; CLI `baxela:install` path owns row creation).
- Toggle the monorepo itself or a non-`baxela-extension` composer package → 422 (V1 slug
  pattern + package-type check both fail).
- Disable while payment requests are in flight on the same Octane worker → driver stays
  resolvable in that worker (D7, documented; no mid-worker revocation).
- Updates proxy with zero installed extensions → `installed: {}` is valid (V8); empty
  `data`; UI shows "no extensions installed".
- Marketplace returns 422 (bad semver) → proxy surfaces `marketplace_error` with the
  message, 200 envelope (it's a deployment bug, not a merchant action).
- `core` version newer than every release's compat → per-slug `no_compatible_release`
  states flow through untouched — the UI labels them "requires newer core", never "broken".
- `current_yanked: true` → badge "your installed version was yanked" with warning styling
  (distinct from update-available).
- Credential set for a driver that is not installed/registered → accepted and stored (an
  extension may be installed later); UI shows the block only for registered drivers.
- Duplicate `(driver, key)` write → upsert (unique constraint + `updateOrCreate`).
- Delete the only DB credential with no env fallback → key-state `none` →
  `is_configured` flips false → the storefront payment method disappears (existing
  behavior, now reachable from the UI — confirm dialog on delete says so).
- APP_KEY rotated between writes → stored blobs `undecryptable` on read (FX precedent);
  `is_configured` treats `undecryptable` as **not configured** (fail closed).
- Stale-while-unreachable proxy responses must carry `cached: true` + `generated_at`
  so the UI can label freshness honestly.

## 5. Tests (Pest, SQLite; `nice -n 10`)

- **P5.1 — `Modules/Core/tests/Feature/ExtensionToggleApiTest.php`**: guest 401; 403
  without `core.admin.extensions.enable`; toggle flip persists + response shape
  (`ExtensionResource` fields incl. derived `skip_reason`); unknown slug 422; installed
  but not extension-type 422; permission `core.admin.extensions.disable` distinct; after
  disable, `shouldBoot` returns false (guard-level, mirroring `ExtensionBootGuardTest`).
- **P5.2 — `Modules/Payment/tests/Feature/DriverCredentialsTest.php`**: guest/403; PUT
  stores rows; response **never contains plaintext** (assert no `value` keys anywhere);
  key-states: `none` → `setting` after PUT → `undecryptable` after APP_KEY rotation
  (re-encrypt approach: write with old key, rotate in test env) → `env` when only env
  exists; `is_configured` flips true with DB-only credential (no env) via the resolver;
  delete → env fallback; upsert on duplicate; webhook-verification credential keys
  included in states.
- **P5.3 — `Modules/Core/tests/Feature/ExtensionUpdatesProxyTest.php`**: `Http::fake` +
  `preventStrayRequests`; payload sent is exactly `{"core": "0.1.0", "installed": {...}}`
  from live composer state; passthrough shape preserved; 15-min cache honored (second
  call = zero HTTP); toggle forgets the cache; unreachable → `marketplace_unreachable`
  + stale payload flag; empty `MARKETPLACE_URL` → `marketplace_unconfigured` (no HTTP
  attempted); marketplace 422 → `marketplace_error`; guest/403.
- **P5.4 — admin i18n parity script green; typecheck/build once** (no new e2e slice —
  D5 Playwright stays the regression net later).
- **P5.5 — marketplace: `UpdateEndpointThrottleTest`** (61st request/min → 429; the
  registry token-guarded routes unaffected).

## 6. Build order

### Slice P5.1 — Extension write endpoints (backend)
- [ ] `POST /{slug}/enable` + `POST /{slug}/disable` in `Modules/Core/routes/api/admin.php`
      (D4 shape; controllers + `ToggleExtensionAction` wrapping slug validation →
      `ComposerPackages` type/installed checks → `toggle()` → `flush()` → resource).
- [ ] Error code `core.extensions.not_installed` (Core ErrorCodeEnum + lang `en`).
- [ ] Tests (§5 P5.1). Bruno: enable/disable requests in the core collection.

### Slice P5.2 — Payment credential store (backend)
- [ ] Migration `payment_driver_credentials` + schema consts + model (encrypted cast, D5).
- [ ] `ResolvesPaymentCredentials` + `PaymentCredentialResolverInterface` (Core contracts,
      D6): key-state map per driver, resolution order, per-call decrypt.
- [ ] `PaymentDriverManager::isConfigured()` → resolver-backed (D6); clients/drivers keep
      reading config — the resolver *overrides* the env value at the single config-read
      point per client construction (verify each `*Client` reads via one accessor; ⚠ if a
      client reads `config()` directly in >1 place, funnel through the accessor first).
- [ ] `GET/PUT/DELETE api/v1/payment/admin/credentials` routes + controller/action
      (write-only values; states in responses; audit via existing payment module events
      if present, else plain audit-log rows on mutation).
- [ ] Tests (§5 P5.2).

### Slice P5.3 — Updates proxy (backend)
- [ ] `config/core.php`: `extensions.marketplace_url` (D2).
- [ ] `CheckExtensionUpdatesAction` + `GET api/v1/core/admin/extensions/updates`:
      collect slugs/versions → call marketplace (5 s timeout, D1) → envelope
      `{availability, cached, generated_at, data}` (D3).
- [ ] Cache key + flush hooks in P5.1's toggle action and the credential mutations
      (D3 — credential changes don't alter versions; only toggle/install/remove flush —
      ⚠ revisit if credential rows ever affect update payloads; today they don't).
- [ ] Tests (§5 P5.3).

### Slice P5.4 — Admin "Extensions" tab (apps/admin)
- [ ] `features/setting/settings/components/extensions-tab.tsx` mounted outside the form
      (D8): extensions table + toggles (confirm dialog, D7 worker-staleness note),
      credential blocks per registered ConfigurableDriver (masked inputs, state chips,
      delete-with-warning), updates badges + changelog links + availability banner.
- [ ] API client additions: `core/admin/extensions` (exists) + enable/disable +
      `payment/admin/credentials` + `core/admin/extensions/updates`.
- [ ] i18n ×6 (`setting/setting.json` label keys + new `payment/credentials.json`),
      parity script green; `pnpm --filter admin run build` once.

### Slice P5.5 — Marketplace throttle + docs
- [ ] `baxela-marketplace`: throttle the two public catalog routes (D10) + test.
- [ ] `docs/EXTENSION_MARKETPLACE_ROADMAP.md`: tick Phase 5 items with dates + the
      D5 Setting-module wording deviation note + D7 Octane note.
- [ ] Bruno: extensions + credentials + updates requests.

## 7. Verification

1. `nice -n 10 sh scripts/test.sh Modules/Core/tests Modules/Payment/tests` — new suites
   green, existing `ExtensionAdminApiTest`/`ExtensionBootGuardTest` unbroken.
2. Full backend suite once; `composer analyse` zero new errors; Pint on changed files.
3. `pnpm --filter admin run build` once (P5.4); i18n parity + usage scripts green.
4. Manual proof on the dev compose: toggle sandbox-gateway off → storefront method list
   drops it (new worker / FPM) → toggle on → returns; set a DB credential for stripe with
   empty env → `is_configured` true on the methods admin page; badge states against the
   locally served marketplace (up-to-date, update-available after a new tag, unreachable
   when stopped).

## 8. Suggested commits (only when asked)

`feat(backend/core): extension enable/disable endpoints (marketplace P5.1)` ·
`feat(backend/payment): db-backed encrypted driver credentials (P5.2)` ·
`feat(backend/core): marketplace updates proxy with cache (P5.3)` ·
`feat(admin/setting): extensions tab with credentials and update badges (P5.4)` ·
`chore(api): bruno collection for extension management (P5.5)` ·
`build(infra): throttle marketplace catalog endpoints (P5.5)`

**Scope guard:** no auto-update machinery, no licensing, no storefront changes, no new
marketplace features beyond the throttle, no driver contract changes.
