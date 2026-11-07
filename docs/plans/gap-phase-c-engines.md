# Phase C — Engine work: tax engine, outbound webhooks + tokens, image variants

> **Purpose of this document:** Self-contained execution plan for **Phase C** of
> `docs/PLATFORM_GAPS_ROADMAP.md` — the three engine workstreams, split into five
> implementation slices. Like gap-b1/gap-b2, this document stands alone: verified
> state, design decisions, build order, edge cases and tests — readable without
> conversation history and handable to an external review before implementation.
>
> **Created:** 2026-11-07 · **Owner:** solo maintainer · **Status:** planned, awaiting
> external review · **All code facts verified 2026-11-07** by three parallel
> explorations (tax/address/seeds · events/sanctum/queue · media/storefront images).
>
> **Execution order (owner-confirmed):** **C.3 → C.2 → C.1a → C.1b → C.1c** — quick
> wins first, checkout-touching tax work last. Slices are strictly sequential: each
> lands green and committed before the next starts. Estimated sizes: C.3 ≈ 1–1.5 d,
> C.2 ≈ 2–3 d, C.1a ≈ 2 d, C.1b ≈ 1–1.5 d, C.1c ≈ 1–1.5 d.
>
> **How to use:** Re-verify ⚠-marked facts before relying on them. Conventional
> commits; never commit unless asked. Heavy commands `nice -n 10`. Tests scoped per
> module during work, full suite once per slice.

---

## 1. Where this fits

| Slice | Content | Order | Target | Why then |
|---|---|---|---|---|
| C.3 | Image variants (queued generation, srcset-ready payloads) | 1st | v0.2 | Smallest, fully independent of checkout; immediate LCP win |
| C.2 | Outbound webhooks + S2S API tokens | 2nd | v0.3 | Unlocks ERP/mobile integrations early; builds on the existing event system only |
| C.1a | Regional tax rates (ISO 3166-2) | 3rd | v0.2–v0.3 | The strategic gap; touches checkout + addresses — done after the lighter slices |
| C.1b | Tax-inclusive pricing | 4th | v0.3 | Builds directly on C.1a's engine work |
| C.1c | VAT ID / B2B reverse charge | 5th | v0.3 | Completes the West-facing tax story; depends on C.1a's address/region plumbing |

**Handoffs:** B.1 Phase 3 will add `RefundFinalizedEvent` — it slots into the C.2
webhook catalog as `refund.finalized` the day it lands (C.2 v1 ships with the
existing `PaymentRefundedEvent`). B.1 Phase 2's invoice `buyer_identity` is where
C.1c's VAT ID surfaces on documents. Phase C's additive-only schema discipline is
bound by the marketplace roadmap §5.2 the moment a third-party extension exists.

## 2. Verified state register (2026-11-07)

### C.3 — Media & images

| Fact | State | Evidence |
|---|---|---|
| Upload write site | ✅ single, synchronous | `Modules/Media/app/Actions/Admin/Media/CreateMediaAction.php` — `$file->store()` on the default disk; zero image processing (no GD/Intervention/getimagesize anywhere) |
| Post-upload hook | ✅ exists | `MediaCreatedEvent` (Core contracts) carries `disk, path, filename, extension, mime_type, size`; Media module's `EventServiceProvider` already maps it (auto-discovery on) |
| Free storage for variant info | ✅ | `media.metadata` text column, cast to `array`, **currently unused by upload** — no migration needed |
| Delete path | ✅ single-file per record disk | `DeleteMediaAction` → `Storage::disk($record->disk)->delete($record->path)`; variants must be enumerated here |
| Queue convention | ✅ listeners, not Jobs | No `Jobs/` dir anywhere; async = `ShouldQueue` + `Queueable` listeners on the `database` connection; tests run `QUEUE_CONNECTION=sync` so listeners execute inline |
| Image capability | ✅ GD everywhere, no lib | `gd` in CI extensions + both Dockerfiles; `intervention/image` absent from composer |
| Media→entity attachment | ⚠ mixed | Catalog `catalog_images` join (`media_id` **loose, no FK**, denormalized `url` col, `photos` collection); categories/brands/posts carry `image_media_id` + `image_url` columns |
| Public payloads | ❌ single URL only | `ListProductResource.image_url`, `ShowProductResource.images[]` + per-variant `image_url`, `Category/Brand/PostResource.image_url` — no variants |
| Storefront rendering | ✅ plain `<img>`, sites known | `product-card.tsx`, `product-gallery.tsx` (main + 4-col thumbs), `post-card.tsx`, `blog/[slug]` cover, home tiles; CSP `img-src` already allows https; no `remotePatterns` config; the one `sizes=` is the local hero |
| Admin media UI | ✅ surfacing points known | `media-card.tsx` / `preview-sheet.tsx` via `getMediaUrl()`; `MediaItem` schema lacks dimensions/variants |

### C.2 — Events, sanctum, queue

| Fact | State | Evidence |
|---|---|---|
| Event inventory | ✅ rich, exact payloads known | `Modules/Core/app/Contracts/Events/**` — Order/Payment/Shipment/OrderReturn families; `AbstractBaseEvent` + `fill()`/`toArray()`; refund catalog event = `PaymentRefundedEvent` (`amount`, `full`, `transaction_id`); **`RefundFinalizedEvent` does not exist yet** (B.1 Phase 3) |
| Dispatch timing | ⚠ **inconsistent** | Several events fire **inside** open transactions (`OrderGateway::createFromCart/markAsPaid/applyRefund`, `CancelOrderAction`); others after commit (refund actions, shipment actions, `HandleWebhookAction`); `queue.after_commit => false`; no `$afterCommit` usage anywhere → webhook listeners must set it themselves |
| Listener registration | ✅ canonical pattern | Module `EventServiceProvider` `$listen` maps (Notification ~19 keys; Audit ~40 → one generic listener, discovery off there); queued listeners = `ShouldQueue` + `Queueable` |
| Sanctum | ⚠ abilities never used | Tokens always `['*']`; no ability middleware wired; no `PersonalAccessToken` model override; no token-management surface (only user "sessions" list/sign-out); admin authorization = spatie permissions from route names (`PermissionMiddleware` + `auth:sync-permissions`) |
| Rate limiting | ✅ pattern established | `RateLimiter::for('<name>', fn (Request $r) => Limit::perMinute(config(...))->by(...))` in module providers + `throttle:<name>` routes (webhook, auth-*, cart-public, contact, support) |
| HTTP client conventions | ✅ thin wrappers + raw-body HMAC precedent | Payment `*Client` classes ("tests only ever mock this class"); inbound webhook verification: Razorpay `hash_hmac` over **raw bytes**, Stripe SDK constructEvent, Checkout.com hex/base64, Telegram constant-time compare; `->timeout()` used only by TelegramClient (15s); no `->retry(` anywhere; `Http::fake` used in Notification tests |
| Module scaffold | ✅ Audit precedent | `Modules/Audit` layout (Actions/Controllers/Models/Schemas/Transformers/routes/lang×6/tests + `module.json` + `modules_statuses.json`), `Nwidart ModuleServiceProvider` with Event/Route sub-providers |
| Admin CRUD pattern | ✅ Audit + Tax precedents | Single-action controllers, spatie QueryBuilder lists, FormRequests (fixed shapes, no `sometimes`), route-name permissions, `baseUrl()`/`superAdminUser()` HelperTraits |

### C.1 — Tax, addresses, seeds

| Fact | State | Evidence |
|---|---|---|
| Rate scoping | ❌ country-only | `tax_rates`: `country_code char(2)` + unique `(country_code, tax_class_id)`; **no region column anywhere** (grep clean across Tax/User/Order) |
| Resolution | ✅ exact-match, integer-exact | `TaxGateway::calculateForItems`: all classes keyed, rates `where(country)->where(is_active)->keyBy(class_id)`; missing/inactive rate = **valid zero**; dangling class = loud failure; coupon allocated largest-remainder BEFORE rates; shipping taxed only via the `is_shipping` class |
| Math | ✅ untouched by Phase C | `TaxMath`: `rateUnits` (regex, percent ×10⁴), `taxOf` (half-up once per line), `allocate` — reused as-is |
| Checkout wiring | ✅ single call site | `Cart/CheckoutAction` builds `TaxCalculationInput` (items + `country_code` from the customer address + coupon + shipping) inside the checkout transaction; tax preview reuses the same calculator |
| Address chain | ❌ **no region field** | `user_addresses` + `order_addresses` + `AddressDto`: `full_name, phone, address_line, city, postal_code, country_code` only; storefront `address-manager.tsx` comment: the design's State select "is the backend's required country select" — a built-but-unwired assumption |
| Seed pattern | ✅ + one cautionary tale | `countries.php` flat rows → `insertOrIgnore` in `CoreDatabaseSeeder`; **`time_zones.php` is dead data** (no consumer) — the regions seed must be consumed, not just shipped |
| VAT/company fields | ❌ none | No vat/company identifier anywhere in backend or storefront |
| Tax admin surface | ✅ complete pattern to extend | Routes/controllers/actions/`TaxRateRequest` (unique per country×class)/`TaxRateResource`/`TaxTransfer::rateRules`/`tax-rate.json` i18n ×6/`tax-rates/data/schema.ts` |

## 3. Slice C.3 — Image variants

### Design decisions

| # | Decision | Why |
|---|---|---|
| D1 | **`intervention/image` v3, GD driver** — pure PHP, no system binaries. | Shared-hosting path stays viable; GD already present in CI + both Dockerfiles. |
| D2 | **Presets in `Modules/Media/config/images.php`:** `thumb 400w`, `medium 800w`, `large 1600w`, quality 82. Applied to jpeg/png/webp sources only; **gif and non-images skipped** (animated gifs must not be re-encoded). | Deterministic, config-driven; skip-list avoids the classic animated-gif corruption bug. |
| D3 | **`GenerateImageVariantsListener`** (`ShouldQueue` + `Queueable`, auto-discovered) on `MediaCreatedEvent` → `GenerateMediaVariantsAction`. Under `QUEUE_CONNECTION=sync` (tests) it executes inline — assertions are direct. | The codebase's established async convention; zero new infrastructure. |
| D4 | **Variants are sibling files on the SAME disk** as the original: `{dirname}/{filename}-{preset}.{extension}`. Originals untouched. | Disk-consistency with the record's `disk` column (the `MediaDiskTest` contract); S3 needs no extra config. |
| D5 | **Variant bookkeeping in `metadata` JSON** (no migration): `{width, height, variants: {thumb: {path, size, width, height}, …}}`. | The column exists, is unused, and is already cast to array. |
| D6 | **Exposure:** `MediaResource` gains `width/height/variants` (urls resolved per record disk, same as `url`); new `MediaGatewayInterface::getVariantsByIds(array $mediaIds): array` serves Catalog's loose `media_id` refs; `ListProductResource`/`ShowProductResource`/`PostResource`/`Category`/`Brand` payloads attach `variants` where the media row resolves — **fallback = original URL only** (legacy rows without `media_id` keep working). | Correctness over coverage: a missing media link degrades to today's behavior. |
| D7 | **`DeleteMediaAction` enumerates variant paths from `metadata`** and deletes them with the parent, on the record's disk. | D4's inverse; the one place variants could orphan. |
| D8 | **Storefront:** `ApiImage`/`ApiProduct`/`ApiPost` gain `variants?: {thumb?, medium?, large?}`; render sites — product card (`thumb` + `srcset thumb/medium`, `sizes` attr), gallery main (`large`) + thumbnails (`thumb`), blog card (`thumb`), post cover (`large`); JSON-LD keeps the largest/original via `absoluteAssetUrl`. Admin `media-card` uses thumb; `preview-sheet` shows dimensions. | The four highest-traffic render sites first; no config changes needed (CSP already allows https). |

### Work items
- [ ] composer: `intervention/image`; `Modules/Media/config/images.php` presets (D2).
- [ ] `GenerateMediaVariantsAction` + queued listener on `MediaCreatedEvent` (D3–D5):
      read original from record disk → encode presets → write siblings → update
      `metadata`. Failures log + report, never break the upload response (variants are
      additive; the original is already stored).
- [ ] `DeleteMediaAction` variant enumeration (D7); `MediaResource` exposure (D6).
- [ ] `MediaGatewayInterface::getVariantsByIds()` + Catalog/Content payload attachment.
- [ ] Storefront: types + the four render sites (D8); admin media card/preview.
- [ ] Pest: both disks (mirror `MediaDiskTest`), derived paths, metadata map, gif skip,
      delete enumeration, inline-listener invocation; admin i18n ×6 for dimension labels.

### Edge cases
Legacy media rows (no `media_id` linkage or missing metadata) → original URL only ·
SVG (vector, skip raster variants) · tiny originals smaller than a preset → skip that
preset rather than upscale · S3 outage → listener failure is logged, original serves ·
deleted folder → variants die with the media row via D7.

## 4. Slice C.2 — Outbound webhooks + S2S API tokens

### Design decisions

| # | Decision | Why |
|---|---|---|
| D1 | **New `Modules/Webhooks` module** (Audit scaffold: Actions/Models/Schemas/Controllers/routes/lang×6/tests, `module.json`, `modules_statuses.json`). | Smallest coherent home; Audit proves the layout; keeps delivery machinery out of Order/Payment. |
| D2 | **Tables:** `webhook_endpoints` (name, url, secret, `events` JSON, `is_active`, `consecutive_failures`, created_by) + `webhook_deliveries` (endpoint FK, event_type, payload JSON, status `pending|success|failed`, attempts, response_status, response_snippet, delivered_at). | Endpoints are merchant config; deliveries are the audit/observability ledger (Audit precedent). |
| D3 | **v1 event catalog — existing events only:** `order.created` (OrderCreatedEvent), `order.paid` (OrderPaidEvent), `order.cancelled` (OrderCancelledEvent), `order.shipped` (OrderShippedEvent), `shipment.shipped` (ShipmentShippedEvent), `refund.processed` (**PaymentRefundedEvent** — carries amount/full/transaction_id), `return.requested` (OrderReturnRequestedEvent). Payload: `{type, occurred_at, data: event->toArray()}`. `refund.finalized` slots in the day B.1 Phase 3 lands `RefundFinalizedEvent`. | Never invent events that don't fire yet; the catalog is a const map, additive by design. |
| D4 | **Listeners: queued + `$afterCommit = true`** (one thin listener per event → `DispatchWebhooksAction`). | Fixes the verified dispatch-timing inconsistency (several events fire inside open transactions) without touching any dispatch site: a queued after-commit listener cannot read pre-commit state. |
| D5 | **Delivery:** `DispatchWebhooksAction` creates `pending` delivery rows (one per subscribed endpoint) and enqueues **`DeliverWebhookJob`** (`$tries = 5`, `$backoff = [30, 120, 600, 3600]`) — the first Job class in the codebase, justified because retries-with-backoff are a job's job, not a listener's. Job → `WebhookDeliveryClient` (thin `Http` wrapper, **timeout 15s**, mocked in tests). | Mirror of the payment `*Client` convention; retry policy lives in one place. |
| D6 | **Signing: HMAC-SHA256 over the exact raw request body** with the endpoint secret; headers `X-Baxela-Signature: sha256=<hex>`, `X-Baxela-Event`, `X-Baxela-Timestamp`, `X-Baxela-Delivery` (uuid). | Mirrors the inbound Razorpay/Stripe raw-body discipline; receivers verify with `hash_equals`. |
| D7 | **Auto-disable:** 10 consecutive failed deliveries → `is_active = false` (failures counter resets on success; disable is recorded in the delivery log). | A dead endpoint must not tax the queue forever; the merchant re-enables after fixing. |
| D8 | **Admin surface:** endpoints CRUD + `POST /endpoints/{id}/regenerate-secret` + `POST /endpoints/{id}/test` (synthetic `test` event) + deliveries list (filters: endpoint/status/event) + show. Route-name permissions (`auth:sync-permissions` after routes); i18n ×6; Bruno collection. | Audit/Tax CRUD pattern; deliveries are read-only. |
| D9 | **S2S tokens (machine route group):** Auth-module admin endpoints mint/revoke Sanctum tokens with **explicit abilities** (first real ability usage) for a chosen user; new read-only **`api/v1/machine/...`** group (`orders` list/show, `products` list/show, reusing existing actions) behind Sanctum's ability-check middleware + a per-token rate limiter (`RateLimiter::for('machine', by token id)`). Scopes: `orders.read`, `products.read`. | Owner-confirmed shape: clean contract, zero impact on existing routes, abilities finally meaningful. |

### Work items
- [ ] Scaffold `Modules/Webhooks`; migrations (D2); models + schemas + enums.
- [ ] Event catalog const + listeners (D3–D4) + `DispatchWebhooksAction` (D5).
- [ ] `DeliverWebhookJob` + `WebhookDeliveryClient` with HMAC signing (D5–D6); failure
      counting + auto-disable (D7).
- [ ] Admin endpoints CRUD + secret regeneration + test event + deliveries log (D8);
      `auth:sync-permissions`; i18n ×6; Bruno.
- [ ] S2S tokens: Auth admin minting endpoints (D9); `machine` route group + ability
      middleware + rate limiter; scopes documented in the module README.
- [ ] Pest: signature verification; delivery success/failure row states; retry backoff
      (Queue::fake + job assertions); auto-disable threshold; ability enforcement (403
      without `orders.read`); machine rate limit; endpoint CRUD permissions.

### Edge cases
Endpoint URL unreachable/timeout → failed attempt + backoff, never blocks the emitting
transaction (D4) · payload size cap (store trimmed response snippets only) · duplicate
event (queued retry after partial failure) → delivery row is the idempotency unit, the
receiver dedupes on `X-Baxela-Delivery` · event with uninitialized locale properties →
payload omits nulls (`toArray` semantics), never throws · secret regeneration → old
secret invalid immediately (documented).

## 5. Slice C.1a — Regional tax rates

### Design decisions

| # | Decision | Why |
|---|---|---|
| D1 | **Additive `region_code` (char(10), nullable, ISO 3166-2 form `US-CA`) on `tax_rates`; NULL = country-wide default rate.** Unique becomes `(country_code, region_code, tax_class_id)` (old two-column unique replaced) — MySQL/SQLite uniques allow multiple NULL rows, so one country-wide + many region rates coexist per class. | Self-describing codes (country embedded); the constraint keeps one-rate-per-scope semantics. |
| D2 | **Resolution precedence in `TaxGateway`:** for each class, region-specific rate if present+active, else the NULL-region country rate; absent both = valid zero. `TaxCalculationInput` gains `?string region_code`. `TaxMath` untouched. | The one behavioral change; everything else keeps its verified semantics. |
| D3 | **Region flows through the address chain:** additive `region_code` on `user_addresses` + `order_addresses` (checkout snapshot), `AddressDto`, `UserGateway::getAddress`, `AddressRequest` (nullable, validated as a country+region pair by a new `RegionCodeRule` against `core_regions`), and checkout passing region into the tax input. | The verified missing plumbing; the storefront design already assumed a State select. |
| D4 | **Core `regions` table + `regions.php` seed — US (51: 50 states + DC) + Canada (13) + Germany (16)**, following the `countries.php` flat-row + `insertOrIgnore` pattern with a live seeder consumer (the `time_zones.php` dead-data lesson). Public read endpoint `GET /core/public/regions?country_code=US` (cached) feeds the storefront select. | Owner-confirmed seed scope; other countries add via seed/admin later. |
| D5 | **Storefront:** address-manager + checkout inline form gain a region select (visible only when the chosen country has regions; options from the new endpoint); `ApiCountry`/`ApiAddress` types extended; the selected region posts with the address, and checkout's saved address carries it into tax. | Closes the built-but-unwired design assumption. |
| D6 | **Tax admin + transfer:** `TaxRateRequest` (`region_code` nullable + pair validation + updated unique rule), create/update actions, `TaxRateResource`, `TaxTransfer::rateRules` + section data (region joins the natural key), admin `tax-rate.json` ×6 + `tax-rates/data/schema.ts` + mutate drawer + columns. | The verified CRUD pattern, extended. |

### Work items
- [ ] Migration (region column + new unique); schema/model/resource constants.
- [ ] Core: `core_regions` migration + `regions.php` seed (US/CA/DE) + seeder + model +
      public list endpoint + `RegionCodeRule`.
- [ ] `TaxGateway` precedence + `TaxCalculationInput.region_code`; `CheckoutAction`
      passes region; unit tests for precedence/fallback.
- [ ] Address chain: `user_addresses`/`order_addresses` migrations, `AddressDto`,
      gateway mapping, `AddressRequest`, storefront region select + types ×6 locales.
- [ ] Tax admin surface + `TaxTransfer` + Bruno; i18n ×6.
- [ ] Pest: precedence/NULL-fallback matrix; bad country+region pair 422; checkout
      region snapshot (`order_addresses.region_code`); transfer round-trip with regions;
      admin CRUD; public regions endpoint.

### Edge cases
Country with no seeded regions → no select, NULL region, unchanged behavior · region
rate exists but inactive → falls back to country-wide (zero if none) — never the
reverse · address region no longer in `core_regions` (seed drift) → rate resolution
falls back to country-wide; the stored snapshot stays as-is · order address snapshots
freeze the region that was actually taxed (audit discipline).

## 6. Slice C.1b — Tax-inclusive pricing

### Design decisions

| # | Decision | Why |
|---|---|---|
| D1 | **`price_contains_tax` boolean (default false) on `tax_rates`** — the jurisdiction property rides the rate the way `is_shipping` rides the class. | A store selling into the EU prices gross; into the US, net — the rate row is where jurisdiction lives. |
| D2 | **Extraction math in `TaxMath` (integer-exact):** for an inclusive rate, net = ⌊base × 10⁶ ÷ (10⁶ + rateUnits)⌋ (rounding direction pinned by tests), tax = base − net; overflow re-checked (base ≤ 10¹² keeps base×10⁶ < PHP_INT_MAX). | Zero floats, same discipline as `taxOf`. |
| D3 | **Orders gain `prices_contain_tax` (bool, default false)** — additive; invoices copy the flag (B.1 kernel tables get the additive column) so documents stay self-contained. Item snapshots keep the extracted `tax_amount`/`tax_rate` unchanged. | The verified snapshot discipline, extended by one column. |
| D4 | **Storefront + preview:** tax preview and PDP price block surface an "incl. VAT"-style marker (i18n ×6) when the resolved rate is inclusive; the displayed price remains the gross figure the customer pays. | Display parity without per-currency pricing. |

### Work items
- [ ] Migration + schema + `TaxGateway`/`TaxMath` extraction path + `TaxResult` flag.
- [ ] `CheckoutAction` snapshot (`prices_contain_tax`) + orders/invoices additive columns.
- [ ] Tax preview marker + storefront i18n ×6; Bruno; transfer rules.
- [ ] Pest: extraction rounding table; exclusive/inclusive matrix; checkout + invoice
      flag snapshot; preview marker.

### Edge cases
Mixed classes (one inclusive, one exclusive) on one order → per-line flags travel with
each line's rate; coupon allocation happens before extraction, unchanged · inclusive
100% edge (rate = 100) → net = base ÷ 2, pinned by test · zero-rate inclusive row →
tax 0, flag still snapshots the mode.

## 7. Slice C.1c — VAT ID / B2B reverse charge

### Design decisions

| # | Decision | Why |
|---|---|---|
| D1 | **`vat_id` (string, nullable) on `user_addresses` + `order_addresses` (snapshot) + `AddressDto`; collected in the storefront address forms for business customers.** | The B2B identifier travels with billing, exactly like region. |
| D2 | **Offline validation only:** `VatIdRule` + a `Tax`-module format validator (per-country regex for the EU set; storefront locales first), exposed via `TaxGatewayInterface::validateVatId(country, vat): bool` (the `taxClassExists` cross-module precedent). **VIES online check is an extension, never core.** | Format checks are deterministic and testable; live verification is a jurisdictional service (geography-neutral rule). |
| D3 | **Reverse charge decided in `TaxGateway`:** valid VAT ID + billing country ≠ store country → all rates resolve to zero with `reverse_charge = true` on the result; invalid/missing VAT ID → normal tax (never silent). Input gains `?string vat_id` + `?string store_country_code`. | The gateway is the one place that sees the full tax picture; loud-or-correct, matching its exception philosophy. |
| D4 | **Store country setting:** `SettingNameEnum::STORE_COUNTRY_CODE` (admin settings UI, i18n ×6) — the merchant's country for cross-border comparison. | The one missing fact; additive to the Setting module. |
| D5 | **Evidence frozen on the order:** additive `buyer_vat_id` + `reverse_charge` columns on orders, persisted by `CheckoutAction`; B.1 Phase 2's invoice `buyer_identity` picks the VAT ID up when rendering lands. | Audit evidence = the ID that justified zero-rating, frozen at purchase. |

### Work items
- [ ] Address/schema/DTO migrations + validator + contract method + rule.
- [ ] Store country setting (Setting enum + admin settings UI + i18n ×6).
- [ ] `TaxGateway` reverse-charge path + `TaxResult.reverse_charge` + `CheckoutAction`
      persistence (D5) + tax-preview visibility for business customers.
- [ ] Storefront: VAT ID input on address forms (conditional on country), types.
- [ ] Pest: format matrix per country; reverse-charge truth table (same country / no
      VAT / invalid VAT / cross-border+valid → zero + evidence); snapshot assertions;
      preview.

### Edge cases
VAT ID on a shipping address but not billing → billing governs · store country unset →
reverse charge never fires (configuration gap is honest, not silent zero-tax) · lowercased
/spaced input → normalized before validation · UK Northern Ireland protocol and
post-Brexit special cases → out of scope, documented.

## 8. Cross-cutting verification & policies

1. **Per slice:** scoped Pest during work; full backend suite + phpstan neutral
   (baseline-splice pattern for new test files) + Pint once per slice; storefront/admin
   builds when those apps change; i18n parity ×6 whenever admin surfaces change;
   live-compose spot check at slice end (dev stack + Bruno).
2. **Schema discipline:** every migration additive; no destructive changes (the
   marketplace roadmap §5.2 clock is running).
3. **Policies this phase writes down:** (a) *catalogs are events, endpoints are
   subscriptions* — new events join the webhook catalog by adding one map entry;
   (b) *tax configuration failures are loud* — the TaxGateway exception philosophy
   extends to reverse charge and inclusive flags; (c) *unavailable data is omitted,
   never fabricated* (the B.2 honesty rule, restated for image variants and webhook
   payloads).
4. **Suggested commits:** one per phase within each slice — e.g.
   `feat(backend/media): queued image variant generation`,
   `feat(storefront): responsive image variants`,
   `feat(backend/webhooks): outbound signed webhooks with delivery log`,
   `feat(backend/auth): scoped s2s tokens and machine api`,
   `feat(backend/tax): region-scoped tax rates`,
   `feat(backend/tax): tax-inclusive pricing`,
   `feat(backend/tax): vat id validation and reverse charge`.

## 9. Explicit non-goals (restated; silence reads as oversight)

- **AvaTax / TaxJar / VIES** — future `tax-engine` extensions; core does regions +
  inclusive + offline VAT format validation well.
- **Carrier APIs / labels / live tracking** — the `shipping-carrier` extension type;
  untouched here.
- **Multi-warehouse / stock reservations** — unchanged single-stock model.
- **Per-currency price lists** — display conversion stays display-only (B.2 D5 rule).
- **Webhook replay UI, event transformations, per-endpoint payload templates** — the
  delivery log + fixed payloads are v1; transforms would be a paid-extension feature.
- **E-invoicing formats** (Factur-X, Moadian) — extensions (gap roadmap §5).

## 10. Review trail

- v1 (2026-11-07): plan authored from three parallel code explorations; owner
  confirmed seed scope (US+CA+DE), execution order (C.3 → C.2 → C.1a → C.1b → C.1c),
  and the machine-route-group token design. *(External review arc pending — this
  section will record verdicts and deltas, gap-b1/b2 style.)*

## 11. Related docs

- `docs/PLATFORM_GAPS_ROADMAP.md` §Phase C — the seed section this plan elaborates;
  §1 placement table and §6 policies bind the slices.
- `docs/plans/gap-b1-invoices.md` — `RefundFinalizedEvent` (Phase 3) and invoice
  `buyer_identity` (Phase 2) are the two C.2/C.1c handoffs.
- `docs/EXTENSION_MARKETPLACE_ROADMAP.md` §5 — additive-migrations + semver-surface
  policies binding all schema changes above.
- `apps/backend/docs/flows/order-state-machine.md` — where tax snapshots sit in the
  lifecycle.
