# Plan E.0–E.2 — Extension system foundation (DB-driven payment methods, extension runtime, sandbox proof extension)

> **Purpose of this document:** Self-contained execution plan for **Phases 0–2** of
> `docs/EXTENSION_MARKETPLACE_ROADMAP.md` — the only phases executable before the
> marketplace exists. The marketplace (roadmap Phases 3–7) gets its own plan only after
> the E.2 system-proof gate passes. Like gap-b1/gap-c, this document stands alone:
> verified state, design decisions, build order, edge cases, tests — readable without
> conversation history.
>
> **Created:** 2026-11-08 · **Owner:** solo maintainer · **Status:** **E.0–E.2 executed
> (2026-11-09/10). The E.2 system-proof gate PASSED — the marketplace plan (roadmap
> Phase 3) may be written next.** Original sequencing constraint satisfied: Phase C
> landed 2026-11-08.
>
> **Execution order (strict):** **E.0 → E.1 → E.2**. Each slice lands green and committed
> before the next starts. Size estimates are initial, not commitments: E.0 ≈ 2 d,
> E.1 ≈ 3–4 d, E.2 ≈ 1–2 d.
>
> **How to use:** Re-verify ⚠-marked facts against the code before relying on them — the
> code moves. Conventional commits (`type(scope): subject`, scope `backend` /
> `backend/core` / `backend/payment`); never commit unless asked; heavy commands
> `nice -n 10`; tests scoped per module during work, full suite once per slice
> (`composer test` wrapper — plain output, greppable at `storage/logs/pest.log`); Larastan
> before Pest; zero new phpstan errors. **Engine principle (carried from phase C): a
> green happy-path test is not a reliable engine — the acceptance tests below target the
> disabled/incompatible/remove paths, not just install-then-pay.**

---

## 1. Where this fits

| Slice | Roadmap phase | Content | Why this order |
|---|---|---|---|
| E.0 | Phase 0 | Remove `PaymentMethodEnum` from public surfaces; per-driver configuration declaration | Hard prerequisite: no external payment extension can exist while methods are enum cases |
| E.1 | Phase 1 | Extension runtime: `Baxela\Extension` base provider, `extensions` table, `baxela:*` commands, compat guard, `App\Baxela::VERSION` | The system E.2 proves; needs E.0's open driver registry |
| E.2 | Phase 2 | `baxela/baxela-sandbox-gateway` sample extension + system-proof checklist | Proves the whole chain side-loaded; gates the marketplace plan |

Not in this plan (their own plan later): marketplace app (roadmap Phase 3), marketplace
frontend/submissions (4), admin Extensions page (5 — this plan ships only the admin-facing
API endpoints), Zarinpal (6), ecosystem maturity (7).

## 2. Verified state register (2026-11-08)

### E.0 — Payment methods

- ⚠ `PaymentMethodEnum` (`Modules/Payment/app/Schemas/Payment/PaymentMethodEnum.php`):
  8 string cases — `paypal, stripe, adyen, nowpayments, mercadopago, checkoutcom, razorpay, manual`.
- ⚠ Usage is **contained to the Payment module plus one Order test**
  (`Modules/Order/tests/Feature/CouponLifecycleTest.php:101`). Full file list with per-site
  usage notes in §3 work items.
- ⚠ The `payment_payment_methods` table **already exists**
  (`2026_09_24_000000_create_payment_methods_table.php`): `method` (string, unique),
  `is_active` (bool, indexed), `sort_order` (uint). No migration changes needed in E.0.
- ⚠ The table is already the activation gate: `CreatePaymentAction` refuses inactive rows
  (`methodInactive()`); storefront `ListPaymentMethodsAction` filters
  `isRegistered && isConfigured` over active rows; admin `ListPaymentMethodAction`
  lazily `firstOrCreate`s a disabled row per registered enum case.
- ⚠ `PaymentDriverManager` (`Modules/Payment/app/Gateways/PaymentDriverManager.php`):
  `forMethod(PaymentMethodEnum)` / `isRegistered(PaymentMethodEnum)` /
  `supportsRefund(PaymentMethodEnum)` typed to the enum; `isConfigured()` is a hardcoded
  `match` over all 8 cases (lines 54–74) mapping to credential config keys.
  `forName(string)` already exists and is what `HandleWebhookAction` uses.
- ⚠ `RefundableDriver` (`Modules/Core/app/Contracts/Gateways/Payment/RefundableDriver.php`)
  is a marker interface extending `PaymentDriverInterface`; `supportsRefund()` already
  checks `instanceof` — the capability-by-interface pattern E.0 extends.
- ⚠ `PaymentDriverInterface` (`Modules/Core/app/Contracts/Gateways/Payment/`):
  `initiate(PaymentInitiateInput): PaymentGatewayResult`, `handleWebhook(Request): WebhookResult`,
  `refund(PaymentRefundInput): PaymentRefundResult`.
- ⚠ Webhook routing is **already extension-ready**: one parameterized route
  `POST api/v1/payment/webhook/{driver}` (`Modules/Payment/routes/api/webhook.php`,
  `throttle:webhook`) → `HandleWebhookController` → `PaymentDriverManager::forName($driver)->handleWebhook($request)`.
  A new driver needs **no route registration** — only an entry in `config('payment.drivers')`.
- ⚠ `payment_payments.method` and `payment_payment_methods.method` are plain string
  columns carrying enum casts on the models (`Payment.php:30`, `PaymentMethod.php:22`).
- ⚠ Seeders: `PaymentDatabaseSeeder` seeds `manual` + `stripe` as active `PaymentMethod`
  rows (`firstOrCreate` on enum cases).

### E.1 — Extension runtime

- ⚠ No application-version constant exists anywhere (`config/app.php`, `app/`, `.env.example`
  checked). E.1 introduces the first one.
- ⚠ Package discovery is active and unfiltered (`composer.json` `extra.laravel` has empty
  `dont-discover`; `post-autoload-dump` runs `package:discover`). A composer-installed
  package declaring `extra.laravel.providers` is auto-registered with zero platform code.
- ⚠ `wikimedia/composer-merge-plugin` includes `Modules/*/composer.json` — module autoload
  pattern exists but extensions must NOT live under `Modules/` (they are vendor packages).
- ⚠ nwidart's enable mechanism (`modules_statuses.json`, flat map read at boot) is the
  pattern precedent for a boot-time enable check; there is currently no per-extension state
  store. E.1 adds the `extensions` table for this.
- ⚠ `composer/semver` is not a direct dependency of the app (it arrives transitively via
  composer internals) — E.1 adds it as an explicit requirement for the compat check.
- ⚠ nwidart module count is now **21** (the Webhooks module landed with phase-C C.2).
- ⚠ Provider lifecycle constraint: Laravel runs **all** `register()` calls before any
  `boot()`. DB access is only safe from `boot()` onward — the guard and all driver-config
  merging must therefore happen in `boot()`, never `register()`.

### E.2 — Sandbox extension

- ⚠ Composer `path` repositories symlink by default; combined with `package:discover`
  (already in `post-autoload-dump`), a path-mounted extension requires no further wiring.
- ⚠ `HandleWebhookController` always answers `{'data': {'received': true}}`; driver
  resolution failures surface as `PaymentException::methodNotSupported()`.
- ⚠ Settings storage precedent (for later phases, not E.2): per-row `group/type/name/value`
  in the `settings` table, `name` constrained by a DB enum that grows via migration
  (`2026_10_30_000001_add_timezone_to_settings_name_enum.php`).

## 3. Slice E.0 — DB-driven payment methods

### Design decisions

1. **The enum dies; plain strings everywhere.** `PaymentMethodEnum` is deleted. Driver
   identity becomes the string key of `config('payment.drivers')`. The `method` columns
   keep their string type; the enum casts on `Payment` and `PaymentMethod` are removed.
   Rationale: an open system cannot have a closed catalog; strings + registry are the
   contract, exactly as webhook routing already treats drivers.
2. **`payment_payment_methods` stays the store catalog** (existence + `is_active` +
   `sort_order`). It gains no new columns in this slice. Admin listing replaces the
   `PaymentMethodEnum::cases()` loop with `array_keys(config('payment.drivers'))`.
3. **`isConfigured()` becomes per-driver declaration.** New optional contract in Core:
   `Modules\Core\Contracts\Gateways\Payment\ConfigurableDriver` with
   `public function credentialKeys(): array` — returns the config keys (relative to
   `payment.{driver}.`) that must be truthy. `isConfigured(string $driver)` resolves the
   driver from the registry and: no `ConfigurableDriver` implementation → **false**
   (fail closed); empty `credentialKeys()` → **true** (absorbs the `MANUAL` special case);
   otherwise every listed key must be non-empty. Built-in drivers each declare their keys
   (moved verbatim from the current `match`). Extensions declare their own — no core edit
   per driver, ever again.
4. **Manager signatures go string-first.** `forMethod()` is deleted; `forName(string)`
   becomes the single resolver used by checkout, webhooks, and admin surfaces.
   `isRegistered/isConfigured/supportsRefund` take `string $driver`.
5. **Validation keeps its 422 behavior.** `PaymentRequest` replaces
   `new Enum(PaymentMethodEnum::class)` with an `exists` rule against
   `payment_payment_methods.method` (same-module schema constants are allowed) — unknown
   methods still fail validation before reaching the action; unregistered/unconfigured
   methods still fail at the action/manager layer as today.

### Work items (~2 d)

- [x] Add `ConfigurableDriver` contract to `Modules/Core/app/Contracts/Gateways/Payment/`.
- [x] All 8 built-in drivers implement `ConfigurableDriver::credentialKeys()` with the keys
      moved verbatim from the `match` (MANUAL returns `[]`). *(executed with review
      correction: keys are lifecycle-complete — stripe +`webhook_secret`, paypal
      +`webhook_id`, adyen +`hmac_key`, which `handleWebhook()` requires but the old
      match never checked; initiate-only credentials now correctly read as unconfigured)*
- [x] `PaymentDriverManager`: string-first signatures; delete `forMethod()`; rewrite
      `isConfigured()` per decision 3. *(executed with review corrections: unregistered
      → false via an `isRegistered` guard — never throws; unexpected resolution failures
      propagate, not silently false; `CreateRefundAction` (Admin/PaymentRefund) was the
      one external `forMethod` call site)*
- [x] `CreatePaymentAction`: `PaymentMethodEnum::from(...)` → plain string input.
- [x] `PaymentRequest`: enum rule → `exists` rule (decision 5). *(executed with review
      correction: NOT db-only `exists` — accepts slug if registered in
      `payment.drivers` OR a row exists, preserving unknown → 422, registered-no-row →
      `method_inactive`, stale-row → `method_not_supported`)*
- [x] `Payment` + `PaymentMethod` models: drop the enum casts.
- [x] `ListPaymentMethodsAction`: return active rows (strings), drop enum mapping;
      `ListPaymentMethodAction`: iterate registered config keys;
      `UpdatePaymentMethodAction`: string-based guard.
- [x] Transformers: `Transformers/User/Payment/PaymentMethodResource` no longer wraps an
      enum instance (wrap the row / string).
- [x] `PaymentDatabaseSeeder`: seed by string (`manual`, `stripe`).
- [x] `PaymentFactory`: replace `randomElement(PaymentMethodEnum::cases())` with a string list.
- [x] Delete `PaymentMethodEnum.php`.
- [x] Tests: `PaymentDriverManagerTest` datasets keyed off config; replace every
      `PaymentMethodEnum::X` constant in Payment feature tests and
      `Order/tests/Feature/CouponLifecycleTest.php` with string literals; add a
      `ConfigurableDriver` unit test (no impl → false; `[]` → true; missing key → false).
      *(plus review-required rows: the three validation/error scenarios, per-driver
      missing-webhook-credential cases, dynamically registered extension driver in the
      admin list, arbitrary-slug serialization — all in `CreatePaymentTest` /
      `ListPaymentMethodsTest` / `PaymentMethodTest` / `PaymentDriverManagerTest`)*

**Frontend (review addition, executed 2026-11-09):** storefront `ApiPaymentMethod` is an
open union (`| (string & {})`), checkout label/description fall back to the raw slug via
next-intl's `t.has()` (v4 has no `defaultValue`), admin `methodKeys` is i18n-only — the
schema was already string-open. `docs/flows/payment-flow.md` driver section + recipe
rewritten; config registry comment updated; phpstan baseline spliced (enum suppression
removed, Pest-closure counts bumped for the added tests).

### Edge cases

- Driver registered but row missing → checkout refuses (`methodInactive` semantics
  preserved via the row guard); admin list lazily creates the disabled row on next view.
- Row exists but driver unregistered (post-`composer remove` leftover) → storefront filter
  hides it; `UpdatePaymentMethodAction` refuses activation (`methodNotSupported` preserved).
- Enum values were the seed keys — `firstOrCreate(['method' => 'stripe'])` must match old
  rows exactly (values are unchanged strings; no data migration needed).

### Verification (end of slice)

- Full suite green; `composer analyse` zero new errors; pint on touched paths.
- Manual: checkout with `manual` + `stripe` sandbox still completes; webhook
  `api/v1/payment/webhook/stripe` still settles.
- Commit: `refactor(backend/payment): replace PaymentMethodEnum with registry-driven methods`
  (core contract addition can ride the same commit or a leading
  `feat(backend/core): add ConfigurableDriver contract`).

## 4. Slice E.1 — Extension runtime

### Design decisions

1. **State: a `core_extensions` table in the Core module** (schema constants per
   conventions, `Modules/Core/app/Schemas/Extension/ExtensionSchema.php` + migration):
   `slug` (string, unique — the composer name), `enabled` (bool, default true),
   timestamps (`created_at` doubles as installed-at). Nothing else. *(Revised
   2026-11-10 after the owner challenged the original fat row: `version` is read
   live from `Composer\InstalledVersions` — a stored copy drifts the moment anyone
   runs raw `composer update` on a side-loaded install — and the `settings` column
   was cut because roadmap Phase 5 routes extension credentials/settings through
   the Setting module, so it had no consumer. The table holds exactly the one bit
   that isn't derivable from Composer: the merchant's enabled/disabled choice.)*
   No per-extension tables in core.

   **Why a table and not an nwidart-style `extensions_statuses.json` flat file:**
   the file works for E.1's CLI-only surface, but Phase 5's admin toggles write
   state from the web container — a row update is atomic and flows into the
   existing Audit-log/Permission machinery, a web-process file rewrite is not;
   and the approved multi-tenancy plan (`tenant_id` row scoping) turns per-tenant
   enablement into a one-column additive migration instead of a rewrite. Reads
   are hidden behind `ExtensionManager` either way, so the choice is about where
   writes can go later, not about E.1's reads.
2. **Base provider: `Modules\Core\Extensions\Extension`** (abstract, extends
   `ServiceProvider`) with a `final public function boot()`. Extensions must never
   override `boot()`; they implement:
   - `public function manifest(): array` — runtime manifest: `slug`, `name`, `type`,
     `version`, `compat => ['core' => '^1.0']`. (Mirrors the `extra.baxela` composer
     metadata; composer extra is for distribution/marketplace validation, this is runtime truth.)
   - `protected function extensionBoot(): void` — the extension's registrations.
   - optional `public function install(): void` / `uninstall(): void` hooks.
   The final `boot()` sequence: load extension state via `ExtensionManager` →
   **guard** (see 3) → on pass only: merge driver registrations / run `extensionBoot()`.
   Everything happens in `boot()`; `register()` stays empty in the base class — DB is
   not safe in `register()` (§2 lifecycle constraint), and an unguarded config merge in
   `register()` would leak a disabled driver into the registry.
3. **Fail-closed guards, in this order:** (a) row missing in `extensions` → treat as
   disabled with a warning log; (b) `enabled = false` → skip; (c)
   `Semver::satisfies(App\Baxela::VERSION, $manifest['compat']['core'])` fails → skip +
   warning log naming the required range. A skipped extension never registers drivers, so
   `forName()` throws `methodNotSupported`, checkout hides the method (row may exist but
   storefront filter drops it), and the webhook route answers an error — present but inert,
   never fatal.
4. **`ExtensionManager`** (Core): loads the table **once per request** (static cache), and
   writes through it on state changes; commands bust any persistent cache after mutations.
   Also owns the skip-reason map for admin surfacing later (roadmap Phase 5).
5. **`App\Baxela::VERSION`** — new `app/Baxela.php`, class constant `VERSION` (start
   `'0.1.0'`); single source of truth, bumped at releases; docblock states it is the value
   extension manifests are checked against.
6. **Commands** (global `app/Console/Commands/`, `baxela` signature namespace):
   - `baxela:install {package} {--ignore-compat}` — validate
     `^[a-z0-9][a-z0-9_.-]*/baxela-[a-z0-9][a-z0-9_.-]*$` → stream
     `composer require <pkg> --no-interaction` via Symfony Process → `php artisan migrate
     --force` → discover the provider (read `vendor/composer/installed.json` for the
     package's `extra`), create the `core_extensions` row (enabled; no version stored —
     read live), call `install()` hook via the resolved provider →
     bust caches. Refuses packages whose `type` ≠ `baxela-extension`. `--ignore-compat`
     only bypasses the install-time hard stop; the runtime guard still no-ops the
     extension by re-evaluating semver live at every boot — nothing is persisted.
   - `baxela:remove {package}` — disable → `uninstall()` hook → stream `composer remove`
     → delete row → bust caches.
   - `baxela:update {package}` — stream `composer update <pkg>` → `migrate --force` →
     bust caches. No version refresh needed — the new version is live the moment
     Composer finishes. **No downgrade support** (roadmap policy §5.3).
   - `baxela:enable {package}` / `baxela:disable {package}` — toggle + bust caches.
   - `baxela:list` — table of slug, version (read live from
     `Composer\InstalledVersions`), enabled, compat, skip-reason (when inert).
7. **Composer runs via `Process`, never in-process** — streamed to the console; the app
   container already ships the composer binary; `post-autoload-dump → package:discover`
   keeps provider registration automatic.

### Work items (~3–4 d)

- [x] `composer require composer/semver` (explicit dependency). *(done 2026-11-10, pinned
      `^3.6` — installed via a scratch `COMPOSER_HOME` because the machine's global GitHub
      OAuth token is dead and poisoned dist downloads; renew or remove it before E.2's
      front-door install, which shells composer for real)*
- [x] `app/Baxela.php` + release-process docblock note.
- [x] `ExtensionSchema` + migration (`core_extensions` — `slug` + `enabled` +
      timestamps only, per decision 1), Core module.
- [x] `Modules\Core\Extensions\Extension` base provider (final `boot()`, guards, hook call).
- [x] `ExtensionManager` (state load/write-through, skip-reason map).
- [x] Five commands per decision 6, sharing a `Concerns\InteractsWithComposer` (Process streaming).
      *(executed as six classes — `baxela:enable`/`baxela:disable` are separate commands per
      the plan's signatures; they live in `Modules/Core/app/Console/Commands/` like every
      other command, not a new global `app/Console/Commands/` — the repo has no such
      directory and house convention is module placement)*
- [x] Admin-facing read API: `GET api/v1/core/admin/extensions` (list with state +
      skip-reason) behind `PermissionMiddleware` route naming (`api.core.admin.extensions.index`),
      ready for the Phase 5 admin page. No write endpoints yet — enabling/disabling via CLI only.
      *(route name follows house convention: `extensions.list`, like `failed-jobs.list` —
      permission `core.admin.extensions.list`)*
- [x] Tests (hybrid level per backend AGENTS.md): guard unit tests (missing row / disabled /
      compat fail each produce the inert behavior with a stub provider); manager
      state-cache test; command tests for slug validation and row upsert (composer steps
      mocked — no shelling in tests); feature test that a disabled driver's webhook returns
      the `method_not_supported` error and its method disappears from the storefront list.
      *(4 files, 22 tests: `ExtensionBootGuardTest` incl. the webhook-inert case,
      `ExtensionManagerTest`, `BaxelaCommandsTest` via `Process::fake` + container-mocked
      `ComposerPackages`, `ExtensionAdminApiTest` incl. permission grant on its own)*

**Executed 2026-11-10.** Deviations found during verification, all folded back: the
state-cache test originally asserted staleness before any read had snapshotted the state
(fixed: read → out-of-band write → still stale → flush); `ComposerPackages::version()`
initially threw for unknown packages (`InstalledVersions` throws — now guarded by
`isInstalled()`); and `baxela:list`'s output test cannot use two
`expectsOutputToContain` calls (the framework matches one Mockery expectation per output
chunk and both substrings share the table-row chunk — the test captures full output via
`Artisan::call()` instead).

### Edge cases

- Extension installed but its row missing (hand-deleted) → guard treats as disabled
  (decision 3a); `baxela:list` shows it as inert with reason.
- Two extensions claiming the same driver key → last-registered wins; `baxela:list` cannot
  detect it in E.1 (documented known limitation; marketplace uniqueness makes it moot later).
- `composer require` fails mid-stream (network, resolution) → command must leave no
  `extensions` row and report composer's stderr verbatim.
- Extension `boot()` throwing → `report($e)` + rethrow as today (bootstrap stays loud for
  genuinely broken code; the guards only absorb *policy* skips, not crashes).
- `modules_statuses.json` is untouched by all of this — modules and extensions are
  different mechanisms; say so in the `baxela:list` help text to prevent confusion.

### Verification (end of slice)

- Full suite green, analyse clean, pint clean.
- Commit: `feat(backend/core): extension runtime with baxela:* commands` (+
  `feat(backend): add app version constant` leading commit if split).

## 5. Slice E.2 — Sandbox proof extension (`baxela/baxela-sandbox-gateway`)

### Design decisions

1. **Separate repo from day one** (roadmap Phase 2 discipline), cloned locally during
   development. Dev mount: add
   `"repositories": [{"type": "path", "url": "extensions/*"}]` to
   `apps/backend/composer.json`, keep `extensions/` in `apps/backend/.gitignore`, clone the
   sandbox repo into `apps/backend/extensions/baxela-sandbox-gateway`. The repositories
   entry is committable and self-documenting; `extensions/` never is.
2. **The sandbox driver is a real payment driver**, not a mock: `SandboxPaymentDriver`
   implements `PaymentDriverInterface` + `RefundableDriver` + `ConfigurableDriver`
   (`credentialKeys()` → `['webhook_secret']`; `config/sandbox.php` defaults the secret
   via `env('SANDBOX_WEBHOOK_SECRET', 'sandbox-secret')` so it is configured out of the box
   yet demonstrably configurable). `initiate()` returns a `payment_url` to a plain
   return endpoint that settles via the same code path a gateway webhook uses;
   `handleWebhook()` verifies an HMAC over the raw body with the configured secret —
   signature verification must be real, this driver is the reference implementation for
   marketplace authors. `refund()` settles the refund record locally (no-op money-wise),
   matching `ManualPaymentDriver` semantics.
3. **It installs through the front door**: the proof checklist uses `baxela:install
   baxela/baxela-sandbox-gateway` (resolving via the path repo), never artisan gymnastics.

### Work items (~1–2 d)

- [x] New repo `baxela-sandbox-gateway`: `composer.json` (`name`, `type: baxela-extension`,
      `extra.laravel.providers`, `extra.baxela` manifest with `compat.core`), license, README,
      semver tag `v0.0.1`. *(executed 2026-11-10 — local git repo (no remote; the marketplace
      ingest is the first remote consumer), tags v0.0.1/v0.0.2; the package declares an
      explicit `version` field because composer's stability gate does not guess versions from
      git tags on path repositories — kept in sync with the tag, README documents it)*
- [x] `src/SandboxServiceProvider.php` extends `Modules\Core\Extensions\Extension`;
      config publish tag; `install()` seeds the `payment_payment_methods` row
      (`firstOrCreate`, inactive-by-default like other drivers); lang errors file; Pest tests.
      *(no publish tag needed — env-backed defaults merge inside `extensionBoot()`, so a
      disabled extension leaves nothing in the payment namespace; `uninstall()` drops the
      method row; tests run from the host backend binary — picked and documented in the
      README, wired via the root `tests/Pest.php` TestCase scope)*
- [x] **System-proof checklist — the E.2 gate, all green before the marketplace plan:**
  *(run live 2026-11-10 against the dev compose stack, through the front door — every
  request went through the HTTP kernel, every mutation through a baxela:* command)*
  - [x] `baxela:install` → row in `core_extensions`, visible in `baxela:list`, sandbox method
    appears in storefront methods (active + configured).
  - [x] Checkout completes with the sandbox driver; `POST api/v1/payment/webhook/sandbox`
    settles an order end-to-end; refund flow settles. *(plus unsigned → 400 invalid,
    signed replay → idempotent ack, refund definitive + order `partially_refunded`)*
  - [x] `baxela:disable` → method vanishes from storefront, webhook returns
    `method_not_supported`, `baxela:enable` restores — no cache-persisted ghosts.
  - [x] Tag `v0.0.2` (trivial change) → `baxela:update` applies and `baxela:list` shows
    the new version (read live — stronger proof than a refreshed stored copy).
  - [x] Compat: temporarily declare `compat.core` above `App\Baxela::VERSION` → install
    blocked with a clear error; force-installed (`--ignore-compat`) copy boots inert.
  - [x] `baxela:remove` → row gone, driver unregistered, core untouched (`composer show`
    confirms).

**The proof found and fixed three real system bugs — exactly its purpose:**

1. **`baxela:install` could not discover the provider it had just required.** The running
   artisan process holds the bootstrap-time autoloader, so the freshly installed provider
   class failed `is_a(..., Extension::class, true)` and the install hook never ran. Fix:
   `ComposerPackages::registerAutoloader()` adds the new package's PSR-4 mappings (paths
   resolved via `InstalledVersions::getInstallPath` — installed.json paths are relative to
   the package root, not vendor/) into the live autoloader before provider resolution.
2. **`baxela:install` pinned the root constraint to the install-time version.** Bare
   `composer require pkg` wrote `^0.0.1` into the backend's composer.json, which then
   forbade 0.0.2. Fix: require with an explicit `:*` constraint — the lock file (never the
   root constraint) is what pins versions deliberately; `baxela:update` moves within it.
3. **Webhook gateway-transaction-ids were never persisted** — `gateway_transaction_id`
   was missing from `Payment::$fillable` since the B.4 column shipped, so the settle
   path's `update()` silently dropped it (mass-assignment's classic silent failure). The
   sandbox proof was the first live webhook settle since. Fix: one fillable line.
4. *(hardening found by the package tests)* **The boot guard queried `core_extensions`
   before fresh databases migrate.** A discovered extension's provider boots during the
   initial artisan call — on a test run (sqlite pre-migration) or a prod cold start, the
   table may not exist. Fix: `ExtensionManager::state()` catches `QueryException` and
   fails closed to "no extensions" — boot never dies over extension state; covered by a
   dedicated test.

**Steady state:** mountable, not installed. The checklist ends at `baxela:remove` by
design, and an always-installed dev extension would change registry-derived test
expectations per machine (a machine with the sandbox mounted would see 9 payment
drivers where CI sees 8). Reinstall is one command when the sandbox gateway is wanted.

### Edge cases

- Path-repo symlink + `optimize-autoloader` interplay: if class discovery misbehaves
  after install, run `composer dump-autoload` once and note it in the sandbox README
  (dev-only caveat; dist installs use real zips later). *(documented in the README;
  never hit during the proof — the registerAutoloader fix covers in-process discovery)*
- Return-URL flow must go through the same `WebhookResult`-shaped settling used by real
  gateways — no private shortcuts, or the proof proves nothing. *(it did: the settle
  went through HandleWebhookAction like any gateway, which is how the fillable bug surfaced)*

### Verification (end of slice)

- Full suite green (sandbox's own tests run from the extension repo's suite or are wired
  into the backend suite — pick one, document in its README); analyse clean. *(host-binary
  run picked and documented; suite green with the sandbox in the removed steady state,
  package suite green while installed)*
- Commits: `feat(backend/compose): mount local extension path repository` +
  the sandbox repo's own history. *(plus `fix(backend/payment)`: the fillable bug, and
  the E.1 command hardening rides the E.2 commit or splits as
  `fix(backend/core): install-time autoloader and constraint hardening`)*

## 6. Cross-cutting policies (restated from roadmap §5, binding during this plan)

1. Core semver promise: within a major, the extension-facing surface (driver contracts,
   `Extension` base class, `ExtensionManager`) is additive-only. E.0/E.1 are the last
   free breaks.
2. No-downgrade for extensions (`baxela:update` never pins below the current version).
3. Fail closed: policy skips are inert-and-logged; crashes stay loud.
4. Extensions never touch `Modules/` or `modules_statuses.json`.
5. First-party-only until the additive-migrations policy kicks in — the sandbox and core
   move together; no third-party package can be listed before marketplace review exists.

## 7. Explicit non-goals (silence reads as oversight)

Marketplace app and registry (roadmap Phase 3) · marketplace frontend/submission (4) ·
admin Extensions page UI and credential forms (5 — only the read API endpoint ships here) ·
credentials migration from `.env` to `settings` (5) · Zarinpal (6) · contracts package
extraction (`baxela/contracts`, Phase 7) · paid/licensed installs · GitLab sources ·
multi-extension driver-collision detection.

## 8. Review trail

- 2026-11-08 — plan drafted from verified state (§2) gathered by code exploration; not yet
  externally reviewed. Suggested next step per house convention: one external review pass
  before starting E.0.
- 2026-11-09 — external review pass 1 (8.5/10): required revisions — validation must
  preserve the registry/DB/config three-layer semantics (not db-only `exists`),
  `credentialKeys()` must cover webhook verification (stripe/paypal/adyen gaps),
  `isConfigured()` must not throw for unknown drivers, frontend `ApiPaymentMethod` +
  checkout fallbacks are in scope, refund action path corrected. Plan revised; every
  code-level claim re-verified against the repo.
- 2026-11-09 — external review pass 2 (9.5/10, approved): two final clarifications —
  `isConfigured()` returns false only for unregistered/unsupported drivers, unexpected
  infrastructure exceptions propagate; validation keeps `string` + `max` constraints on
  top of the registry-or-row rule. Both folded in; E.0 executed the same day (see §3
  work-item notes).
- 2026-11-10 — owner design challenge on the `core_extensions` row: table slimmed to
  `slug` + `enabled` + timestamps (the only state not derivable from Composer);
  `version` read live from `InstalledVersions`; `settings` column cut (Phase 5's
  Setting module owns extension settings); `--ignore-compat` persists nothing — the
  runtime guard re-evaluates live. Flat-file alternative rejected with rationale
  recorded in §4 decision 1.

## 9. Related docs

- `docs/EXTENSION_MARKETPLACE_ROADMAP.md` — master roadmap; this plan executes its Phases 0–2.
- `apps/backend/AGENTS.md` — module conventions (schemas, actions, tests, larastan discipline).
- `docs/plans/gap-phase-c-engines.md` — phase C; finish before starting E.0.
