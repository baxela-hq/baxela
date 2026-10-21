# Phase 1 Plan — v0.1.0-alpha Hardening (Weeks 1–3)

> Execution plan for Phase 1 of `docs/ROADMAP.md`. Prerequisite: Phase 0 complete
> (`docs/plans/phase-0-legal-and-truth.md`). Re-verify line numbers before editing.
> Sections are parallelizable across sessions except where noted; suggested order:
> 1.1 security → 1.2 e2e (locks the happy path) → 1.3 multi-currency → 1.4 locales →
> 1.5 media → 1.6 Scramble → 1.7 tests/baseline → 1.8 cleanup.
>
> **Exit criteria:** security pass done · e2e happy path green in CI · multi-currency and
> new locales demoable · Scramble claim true · Shipping/Menu tested · phpstan baseline
> < 500 · no template leftovers.

## Verified state (2026-10-21) — read first

**Test conventions** (copy the Payment module's shape exactly):
- Module-local tests: `Modules/{Name}/tests/{Feature,Unit}/`. Pest function style: top of
  file `uses(RefreshDatabase::class); uses(HelperTrait::class);` — never re-declare
  `TestCase` (bound globally in `apps/backend/tests/Pest.php`; double registration fatals).
- Shared fixtures live in each module's `tests/Feature/HelperTrait.php`
  (`baseUrl()`, `superAdminUser()`; Shipping's adds `activeZone()`/`activeMethod()` which
  handle the `Country` + `zone_countries` pivot rows). Never use file-level `function`
  fixtures when shared across files (single-process name collisions).
- Body: `$this->actingAs($user)`, `$this->postJson($this->baseUrl('/user/process'), [...])`,
  `->assertOk()` / `->assertJsonPath('code', 'payment.process.invalid_order')`, then
  `expect()` DB-state chains. Gateway clients mocked with `Mockery` +
  `$this->app->instance(...)`.
- Run: `composer test` in `apps/backend` (wraps `scripts/test.sh` → plain-output Pest,
  log at `storage/logs/pest.log`). Env: SQLite `:memory:`, sync queue, array mailer;
  rate limits pre-raised to 1000 in `phpunit.xml` — throttle tests must NOT rely on that
  default (see 1.1).
- `RefreshDatabase` wipes seeded countries/languages/currencies — tests recreate via
  `TestCase::defaultLanguage()` / `firstOrCreate` (follow existing tests).

**Modules/tests reality check** (corrects ROADMAP Appendix A):
- Support has 9 tests in 1 file (thorough), Audit 6, Setting 5 — NOT gaps.
- Real gaps: **Shipping admin surface** (19 controllers: Method 5, Rate 5, Zone 5, Shipment
  4 + user Shipment show — only `ShippingMethodTest` 3 tests + `ShippingGatewayTest` 5 exist)
  and **Menu** (12 controllers, 3 tests: no CRUD-update/delete, no permission-denial).
- phpstan: `apps/backend/phpstan.neon` level 6, paths `app` + `Modules`, single
  `phpstan-baseline.neon`. Baseline entries by module (regenerate before trusting):
  Catalog 830, Payment 290, Content 276, Order 254, Cart 213, Notification 185, Discount 159,
  Shipping 144, Core 137, Auth 135, Support 98, Menu 96, Tax 83, Contact 72, Media 70,
  User 52, Setting 45, Audit 32, Inventory 31. Run: `composer analyse`
  (larastan ^3.13). Regenerate:
  `vendor/bin/phpstan analyse --generate-baseline --memory-limit=1G`.

**Frontends:** neither app has any test framework. Storefront: pnpm 11.21.0, Next 16;
env `NEXT_PUBLIC_API_BASE_URL` (browser, default `http://localhost:8085/api/v1`) +
`SERVER_API_BASE_URL` (SSR in compose: `http://web:80/api/v1`). Admin: Vite, port 5173,
`VITE_API_BASE_URL`. Compose `frontend` profile runs storefront :3000, admin :5173, nginx
API :8085, Reverb :8080; `mail` profile runs **Mailpit** (HTTP API on its mapped port —
check `docker-compose.yml` for the exact port; default 8025).

**Payments e2e leverage:** `manual` driver (`Modules/Payment/app/Gateways/Drivers/ManualPaymentDriver.php`)
returns an empty result (`payment_url` null, no webhook) — checkout completes to a PENDING
order; settlement is admin-side `PATCH api/v1/payment/admin/payments/{id}` →
`UpdatePaymentAction` marks SUCCESS + `markAsPaid` on the order. Checkout itself is an
`auth:sanctum` user route (`Modules/Cart/app/Actions/User/Cart/CheckoutAction.php`) — an
e2e needs a signed-in customer; email OTP can be read from Mailpit's HTTP API.

---

## 1.1 Security pass

1. Enumerate unthrottled auth-ish routes: `Modules/Auth/routes/api/*.php` (OTP
   request/verify, sign-in, password reset), `Modules/Contact/routes/api/public.php`
   (contact submit, newsletter — newsletter already has a rate-limit test). Add named
   throttles mirroring the existing pattern: limiter defined in the module's
   ServiceProvider (see `Modules/Cart/app/Providers/CartServiceProvider.php:35` —
   `RateLimiter::for('cart-public', ...)` keyed by X-Cart-Token/IP) and applied in routes.
2. OTP hardening: per-identifier attempt cap on verify (e.g., 5 → lockout/backoff),
   expiry check, single-use enforcement — locate the OTP action under
   `Modules/Auth/app/Actions/` and add guards if missing.
3. Admin route protection audit test (new file, Core or Auth tests): enumerate all routes
   under `api/v1/*/admin/` via `Route::getRoutes()`; assert each carries
   `auth:sanctum` middleware + `PermissionMiddleware::class`. This turns the audit into a
   permanent regression test covering every module — the single highest-value security test
   in the repo.
4. Tests: throttle hits → 429 with the module's error-code envelope; OTP lockout; audit
   test green (fix any unprotected route it finds — that's the point).

Suggested commits: `feat(backend/auth): throttle otp and auth endpoints`,
`test(backend/core): assert all admin routes require permissions`

## 1.2 Playwright e2e happy path (first frontend tests in the monorepo)

1. `apps/storefront`: `pnpm add -D @playwright/test`; create `playwright.config.ts`
   (baseUrl from env `E2E_BASE_URL`, default `http://localhost:3000`, `storageState`
   reuse for the authed profile flow), `e2e/` dir (outside `app/` so Next ignores it).
   Add scripts: `"test:e2e": "playwright test"`.
2. Local run prerequisite: `docker compose --profile frontend up -d` (+ `mail` profile for
   Mailpit). Seed: ensure manual payment method is active (Payment seeder seeds MANUAL
   sort 10 active — run module seeders on the dev DB if empty).
3. Spec `e2e/happy-path.spec.ts` (guest-visible part): home renders featured → PDP via
   first product link → variant select → add to cart → cart page shows line → checkout:
   address form (country that has an active shipping zone + rate in dev seed — verify which
   country the dev seed covers, default the form to it) → shipping quote loads → manual
   payment method → place order (idempotency header already handled by checkout page).
4. Authed flow: sign up with a fresh email → read OTP from Mailpit HTTP API
   (`GET /api/v2/messages` → latest to that address → parse code) → submit OTP → land in
   profile; place the order → assert it appears under profile/orders.
5. Stretch (separate spec): admin settlement — PATCH the payment via API with an admin
   token (create admin user via seeder/artisan), assert order status transitions in the
   customer's order view.
6. CI: new `.github/workflows/e2e.yml` — trigger `push` to main + `workflow_dispatch`
   (NOT pull_request yet); steps: checkout → `docker compose --profile frontend --profile
   mail up -d` → wait on healthchecks (`/up`, Mailpit API, storefront :3000 200) →
   `pnpm install --frozen-lockfile && pnpm exec playwright install --with-deps chromium` →
   `pnpm test:e2e`. Mark the job `continue-on-error: true` until stable, then flip.
7. Record how dev-seed country/shipping data was satisfied — the spec must not depend on
   manual DB edits.

Suggested commits: `test(storefront): add playwright happy-path e2e`,
`ci(infra): add e2e workflow booting the compose stack`

## 1.3 Multi-currency (display conversion) — the feature

Design principles: **charging stays in the store base currency** (the default currency);
conversion is display-only; order snapshots what was shown. No external FX API.

**Backend:**
1. Migration (Core): add to `currencies` — `exchange_rate decimal(16,8) NOT NULL default
   1.00000000` (semantics: multiply base-minor by rate → target-minor) and
   `is_active boolean NOT NULL default true`. Extend `CurrencySchema` constants + model
   fillable/casts.
2. `Modules/Core/app/Support/Money.php`: add `convert(int $minor, string $rate, int
   $precision): int` — integer math only, half-up, no floats (follow `applyPercent`'s
   style; unit-test rounding edges: rate < 1, 8-decimal rates, precision 0).
3. Admin CRUD (none exists — only `ListCurrencyAction`): create `CreateCurrencyAction` /
   `UpdateCurrencyAction` (fields: name, native_name, code, symbol, is_symbol_right,
   decimal_places, exchange_rate, is_active) + `CreateCurrencyController` /
   `UpdateCurrencyController` + routes in `Modules/Core/routes/api/admin.php` next to the
   existing list route. `UpdateSettingAction` already mirrors `currency_id` →
   `markCurrencyDefault()` — keep that as the only default-switching path. Register new
   permission names in `Modules/Auth/app/Console/Commands/SyncPermissionsCommand.php`
   (follow existing naming, e.g. `core.admin.currencies.create|update`).
4. Public endpoint: `GET v1/core/public/currencies` → active currencies with
   code/symbol/side/decimals/exchange_rate/is_default (extend
   `Transformers/Public/Currency/CurrencyResource` or add a collection resource). Keep the
   existing `/core/public/currency` (default only) untouched for current consumers.
5. Order snapshot: migration (Order) adds `display_currency_code char(3) NULL` +
   `display_fx_rate decimal(16,8) NULL` to `orders`. Extend
   `Core/Contracts/Gateways/Order/DTOs/CreateOrderInput` and
   `Modules/Order/app/Gateways/OrderGateway.php::createFromCart()` (mirror the existing
   `currency_id` snapshot written in `CheckoutAction.php:90-93` and the tax snapshot at
   197–216). Checkout input gains optional `display_currency` (code) — validated against
   active currencies; rate resolved server-side, never trusted from the client.
6. Tests (Core + Order): public list filters inactive; admin create/update + permission
   denial; default-switch still mirrors flags; checkout persists display currency + rate;
   `Money::convert` unit tests.

**Storefront:**
1. Currency preference = **cookie** mirroring the theme-mode pattern in
   `apps/storefront/lib/theme.ts` (server-readable, `path=/; max-age=31536000;
   samesite=lax`) — name `baxela-currency`, value = 3-letter code. Set from a header
   switcher (place next to `components/layout/language-switcher.tsx`), then
   `router.refresh()` so server components re-render.
2. Wire-up: extend `lib/api/site.ts` — implement `fetchCurrencies()` (new endpoint) and
   finally use the dead `fetchCurrency()`/list to resolve the *selected* currency (cookie
   via `next/headers` `cookies()`) with fallback to default. In `i18n/request.ts`, the
   registered `price` number format must reflect the SELECTED currency (symbol/side/
   decimals come from the selected row).
3. Conversion helper in `lib/currency.ts`: `convertPrice(baseAmount: number, ctx)` where
   ctx = selected currency + rate (pass server-resolved ctx to client via props or a
   client provider — this is where the docstring's promised `CurrencyProvider` finally
   becomes real: one React context providing `{ currency, rate, format, convert }`).
4. Refactor the 8 price consumers (all use `format.number(value, "price")`):
   `components/product-card.tsx`, `components/sale-badge.tsx`,
   `components/layout/cart-menu.tsx`, `app/[locale]/(shop)/products/[id]/page.tsx`,
   `app/[locale]/(shop)/cart/page.tsx`, `app/[locale]/(shop)/checkout/page.tsx`,
   `components/account/order-list.tsx`. Convert the *number* before formatting; never
   touch payload values. Server-authoritative amounts (tax preview, coupon, shipping
   quote) convert at display only.
5. Checkout page: pass `display_currency` (selected code) in the checkout POST; add a
   "You will be charged in {base currency}" note when a non-base currency is selected.
6. Update `docs/THIRD_PARTY_SERVICES.md` FX row: manual rates shipped; exchangerate.host /
   Open Exchange Rates remain future upgrades.
7. Playwright: add currency-switch step — switch to a seeded second currency on the PDP →
   price text changes accordingly (extend the 1.2 spec).

Suggested commits: `feat(backend/core): currencies gain exchange rates and admin crud`,
`feat(backend/order): snapshot display currency and fx rate at checkout`,
`feat(storefront): visitor-facing currency switcher with display conversion`

## 1.4 Locale expansion: de, fr, es, ar

**Assumption flagged:** translation strings are agent-drafted from `en`, owner reviews.
Arabic proves RTL beyond fa (`RTL_LOCALES` in `apps/storefront/lib/i18n.ts` already
contains `ar`; Vazirmatn font already loaded for RTL).

Storefront (17 message files per locale — parity script enforces exact key match against
`en` and loader registration, so a partial locale FAILS CI):
1. `pnpm`-free mechanics first: create `messages/{de,fr,es,ar}/` mirroring `en/` files;
   add codes to `locales` in `apps/storefront/i18n/routing.ts`; add NATIVE_NAMES entries
   (Deutsch, Français, Español, العربية) in `lib/i18n.ts`; register each file in
   `i18n/request.ts` `loadMessages()` (explicit imports + nested entries — the parity
   script checks every file on disk appears there).
2. Translate the 4×17 files (draft from `en`; `fa` as tone reference for formal register).
3. Verify: `node scripts/check-i18n-parity.mjs` green.

Admin (46 namespace files per locale under `apps/admin/public/locales/`):
1. Same mechanics: `public/locales/{de,fr,es,ar}/` mirroring `en/`; i18next
   (`src/i18n/index.ts`) discovers locales via `loadPath` — confirm any hardcoded locale
   list there and extend; fallbackLng already `en`.
2. Translate 4×46 files (largest mechanical chunk — do per-feature commits so reviews stay
   small; data-table/shared namespaces first since every page uses them).

Backend:
1. Activate `de`, `fr`, `es`, `ar` rows in the `languages` table (data seeded from
   `Modules/Core/database/data/languages.php`; `ar` already `is_rtl: true`) — small
   migration or seeder update that flips `is_active` for those codes.
2. Known quirk to fix while here: missing DB translation rows fall back to **first row by
   insertion order**, not the default language (e.g.
   `Modules/Shipping/app/Gateways/ShippingGateway.php:98-104`). Change translation
   accessors to prefer the default-language row as fallback (grep the
   `firstWhere(...LANGUAGE_ID...) ?? ->first()` pattern across modules and normalize).
3. Optional (skip if time-boxed): module `lang/{code}` validation-message files — Laravel
   `fallback_locale=en` already covers errors.

Suggested commits: `feat(storefront): add german french spanish arabic locales`,
`feat(admin): add german french spanish arabic locales`,
`fix(backend/core): fall back to default language for missing translations`

## 1.5 Media: finish the S3 driver

1. `composer require league/flysystem-aws-s3-v3` in `apps/backend`. The `s3` disk is
   already fully defined in `config/filesystems.php` (AWS_* env keys, `throw => false`,
   `report => false`) — no config change needed.
2. Disk selection stays `config('filesystems.default')` (env-driven: `FILESYSTEM_DISK=s3`
   for S3 deployments) — document this; skip an admin UI toggle (scope discipline).
3. Fix the two record-disk bugs so multi-disk history stays correct:
   - `Modules/Media/app/Transformers/Admin/Media/MediaResource.php:22` —
     `Storage::url($path)` ignores the record's disk → resolve via
     `Storage::disk($this->disk)->url($path)`.
   - `Modules/Media/app/Actions/Admin/Media/DeleteMediaAction.php:19` —
     `Storage::delete($path)` → delete from the record's disk.
4. Tests: `Storage::fake('s3')` — upload lands on s3 with disk recorded; delete removes
   from the right disk; URL resolves per-disk. Keep existing local-disk tests green.
5. Docs: add S3 env-var block (AWS_*) to backend README configuration section +
   `docs/THIRD_PARTY_SERVICES.md` media row update.

Suggested commits: `build(backend): add s3 flysystem adapter`,
`fix(backend/media): resolve urls and deletes against the records own disk`

## 1.6 Scramble: make the API-docs claim true

Current: v0.13.24 installed + auto-discovered, zero config/registration (default would
serve `/docs/api` local-only with wrong `api_path`). Controllers are invokable with mostly
native return types — good for inference; ~11 controllers lack return types (degrade docs).

1. `php artisan vendor:publish --tag=scramble-config` → `config/scramble.php`: set
   `api_path` to cover `api/v1` (module routes register at `api/v1/{module}/...` via each
   module's RouteServiceProvider), add bearer/sanctum security scheme, keep
   `RestrictedDocsAccess` (local-only by default) + env `SCRAMBLE_DOCS_ENABLED=false`
   for prod off-switch.
2. Register docs route explicitly (skeleton `routes/api.php` or
   `bootstrap/app.php`): `Scramble::routes()` group limited to the v1 prefix; confirm all
   19 modules' route files are picked up (no route cache present — good).
3. Add return type hints to the ~11 untyped controllers: find via
   `grep -rL "__invoke.*): " apps/backend/Modules/*/app/Http/Controllers` (verify list
   manually; expect `: JsonResource` / `: AnonymousResourceCollection` / `: Response`).
4. CI: export `openapi.json` in the backend job (`vendor/bin/scramble export` or the
   artisan equivalent) and upload as an artifact — static API reference for the repo and
   Phase 2 docs page.
5. Re-add the API-docs claims removed in Phase 0.2 (root README bullet, backend README
   Documentation section `/docs/api` link, AGENTS.md line) — now true.
6. Manual verify: `/docs/api` renders locally with all module groups; sample a public,
   a user, and an admin route — security scheme + pagination `links/meta` visible.

Suggested commits: `feat(backend/core): wire scramble openapi docs`,
`docs(monorepo): restore api-docs claims now that scramble is live`

## 1.7 Test backfill + phpstan baseline reduction

Shipping (priority — 19 untested admin controllers):
1. `Modules/Shipping/tests/Feature/Admin/`: ZoneTest, RateTest, MethodTest (CRUD happy
   paths, validation failures, permission denial using a non-privileged admin user — see
   Audit/Support tests for the denial pattern), ShipmentTest (list/show/create/update +
   invalid transition → `InvalidTransitionException` envelope).
2. `Modules/Shipping/tests/Feature/User/`: shipment show by code + ownership denial.
3. Reuse `HelperTrait::activeZone()/activeMethod()`; add rate fixtures there.

Menu: extend `Modules/Menu/tests/Feature/MenuTest.php` — menu/link update + delete,
link delete/update, permission denial on admin routes.

Setting: add permission-denial + invalid-name validation tests to
`Modules/Setting/tests/Feature/SettingTest.php`.

phpstan: regenerate baseline first (get today's true count; ~3,202 as of 2026-10-21).
Then fix-not-suppress module by module, smallest-first for morale (Inventory 31, Audit 32,
Setting 45, User 52, Media 70, Contact 72 …), regenerate after each module, commit per
module. Order per ROADMAP was Core-first; either works — pick smallest-first and note the
decision. Target at alpha tag: **< 500**. Add a CI `phpstan` job (analyse with baseline,
fail on new errors above baseline — the default behavior) so the baseline only shrinks.

Suggested commits: `test(backend/shipping): cover admin crud and shipment transitions`,
`test(backend/menu): cover link updates and permission denial`,
`refactor(backend/{module}): clear phpstan baseline entries` (per module),
`ci(infra): run larastan in ci`

## 1.8 Template/mock cleanup (corrected list — do NOT delete live features)

**Keep:** `routes/_authenticated/catalog/attributes/{groups,templates}.tsx` and
`values/$id/` — these render REAL Baxela features (attribute-groups/templates/values).
The ROADMAP's original leftover list was wrong about them.

Admin removals:
1. `src/routes/(auth)/sign-in-2.tsx` (unlinked), `sign-up.tsx` + mock
   `forgot-password.tsx` (forms `console.log` the payload). Remove their link sites too:
   `features/auth/sign-in/components/user-auth-form.tsx:89` (forgot-password link) and
   `features/auth/forgot-password/index.tsx:33` (sign-up link). Storefront already owns
   customer self-service; admin credentials come from admins — note in CHANGELOG.
2. `src/routes/_authenticated/settings/{display,appearance}.tsx` +
   `features/settings/display|appearance` (template pages). Keep the settings section
   shell — the REAL Baxela store settings live at `settings/store.tsx`
   (`features/setting/settings`); prune template-only sub-pages (profile/account/
   notifications forms) from `features/settings/` after confirming nothing imports them;
   keep `settings/store`.
3. `src/features/users/` (15 files, faker-seeded 500 fake users) + route
   `routes/_authenticated/users/index.tsx` — superseded by `features/user/users`
   (sidebar-linked `/user/users`). Confirm no imports reference `features/users`, then
   delete; also drop the `faker` dependency if now unused.
4. Route file `routes/_authenticated/setting/settings/index.tsx` is a pure redirect to
   `/settings/store` — keep (cheap) or remove (grep history) — decide: keep.

Storefront removals:
5. Delete orphaned `components/account/coming-soon-panel.tsx` (referenced nowhere).
6. Remove the mock saved-cards page + nav entry + `components/account/saved-cards.tsx`
   (in-memory mock) — simpler than building a feature-flag mechanism for one page; note
   in CHANGELOG "returning when card storage ships". Grep nav config for the cards entry.

Verify: `pnpm build && pnpm lint` green in `apps/admin`; `pnpm build` green in
`apps/storefront`; sidebar/nav have no dead links (manual pass).

Suggested commits: `refactor(admin): remove shadcn-admin auth and settings leftovers`,
`refactor(admin): drop template users feature in favor of user module`,
`refactor(storefront): remove mock saved-cards and orphaned panel`

---

## Definition of done

- [ ] Throttle + OTP-lockout tests green; admin-route protection audit test green
- [ ] `e2e` workflow green on main (or `continue-on-error` with a tracking issue)
- [ ] Currency switcher demoable end-to-end; orders snapshot display currency + rate
- [ ] `check-i18n-parity.mjs` green with 6 locales in both apps; `ar` renders RTL
- [ ] S3 upload/delete/URL tests green; record-disk bugs fixed
- [ ] `/docs/api` renders locally; `openapi.json` artifact attached to CI runs
- [ ] Shipping admin + Menu tests merged; phpstan baseline < 500; phpstan CI job live
- [ ] No template leftovers; builds green in both frontends
