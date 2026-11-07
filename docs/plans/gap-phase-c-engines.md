# Phase C — Engine work: tax engine, outbound webhooks + tokens, image variants (v2)

> **Purpose of this document:** Self-contained execution plan for **Phase C** of
> `docs/PLATFORM_GAPS_ROADMAP.md` — the three engine workstreams, split into five
> implementation slices. Like gap-b1/gap-b2, this document stands alone: verified
> state, design decisions, build order, edge cases and tests — readable without
> conversation history and handable to an external review.
>
> **Created:** 2026-11-07 · **Owner:** solo maintainer · **Status:** **in revision
> (v2)** after an external review (7.5/10 — "request one more revision"): all seven
> P0 and nine P1 corrections are incorporated; the review trail (§10) maps each to
> its landing spot. Facts verified 2026-11-07 by three parallel code explorations.
>
> **Execution order (owner-confirmed, unchanged):** **C.3 → C.2 → C.1a → C.1b →
> C.1c**. Slices are strictly sequential: each lands green and committed before the
> next starts. **Size estimates are initial estimates, not commitments** (the
> reviewer's caution is adopted): C.3 ≈ 1.5–2 d, C.2 ≈ 3–4 d, C.1a ≈ 2 d,
> C.1b ≈ 1.5 d, C.1c ≈ 1 d.
>
> **How to use:** Re-verify ⚠-marked facts before relying on them. Conventional
> commits; never commit unless asked. Heavy commands `nice -n 10`. Tests scoped per
> module during work, full suite once per slice.

---

## 1. Where this fits

| Slice | Content | Order | Target | Why then |
|---|---|---|---|---|
| C.3 | Image variants (queued, idempotent generation) | 1st | v0.2 | Smallest, fully independent of checkout; immediate LCP win |
| C.2 | Outbound webhooks (outbox) + S2S API tokens | 2nd | v0.3 | Unlocks ERP/mobile integrations early; builds on the existing event system only |
| C.1a | Regional tax rates (ISO 3166-2) | 3rd | v0.2–v0.3 | The strategic gap; touches checkout + addresses |
| C.1b | Tax-inclusive pricing (store-level mode) | 4th | v0.3 | Builds directly on C.1a's engine work |
| C.1c | VAT ID collection + offline validation | 5th | v0.3 | Completes VAT collection; **automatic reverse charge defers to a verification policy** (see §7) |

**Handoffs:** B.1 Phase 3 will add `RefundFinalizedEvent` — it slots into the C.2
webhook catalog as `refund.finalized` the day it lands (C.2 v1 ships with the
existing `PaymentRefundedEvent`). B.1 Phase 2's invoice `buyer_identity` copies the
VAT ID **from the frozen order snapshot at invoice creation** — never from the
customer's current address. Phase C's additive-only schema discipline is bound by the
marketplace roadmap §5.2 the moment a third-party extension exists.

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
| Upload limit | ✅ 20 MB at the request layer | `MediaRequest`: `File::types(...)->max('20mb')` — dimensions/pixel limits still needed inside the variant generator |
| Media→entity attachment | ⚠ mixed | Catalog `catalog_images` join (`media_id` **loose, no FK**, denormalized `url` col, `photos` collection); categories/brands/posts carry `image_media_id` + `image_url` columns |
| Public payloads | ❌ single URL only | `ListProductResource.image_url`, `ShowProductResource.images[]` + per-variant `image_url`, `Category/Brand/PostResource.image_url` — no variants |
| Storefront rendering | ✅ plain `<img>`, sites known | `product-card.tsx`, `product-gallery.tsx` (main + 4-col thumbs), `post-card.tsx`, `blog/[slug]` cover, home tiles; CSP `img-src` already allows https; no `remotePatterns` config; the one `sizes=` is the local hero |
| Admin media UI | ✅ surfacing points known | `media-card.tsx` / `preview-sheet.tsx` via `getMediaUrl()`; `MediaItem` schema lacks dimensions/variants |

### C.2 — Events, sanctum, queue

| Fact | State | Evidence |
|---|---|---|
| Event inventory | ✅ rich, exact payloads known | `Modules/Core/app/Contracts/Events/**` — Order/Payment/Shipment/OrderReturn families; `AbstractBaseEvent` + `fill()`/`toArray()`; refund catalog event = `PaymentRefundedEvent` (`amount`, `full`, `transaction_id`); **`RefundFinalizedEvent` does not exist yet** (B.1 Phase 3) |
| Dispatch timing | ⚠ **inconsistent** | Several events fire **inside** open transactions (`OrderGateway::createFromCart/markAsPaid/applyRefund`, `CancelOrderAction`); others after commit (refund actions, shipment actions, `HandleWebhookAction`); `queue.after_commit => false`; no `$afterCommit` usage anywhere |
| Listener registration | ✅ canonical pattern | Module `EventServiceProvider` `$listen` maps (Notification ~19 keys; Audit ~40 → one generic listener, discovery off there); queued listeners = `ShouldQueue` + `Queueable` |
| Sanctum | ⚠ abilities never used | Tokens always `['*']`; no ability middleware wired; no token-management surface (only user "sessions" list/sign-out); admin authorization = spatie permissions from route names (`PermissionMiddleware` + `auth:sync-permissions`) |
| Rate limiting | ✅ pattern established | `RateLimiter::for('<name>', fn (Request $r) => Limit::perMinute(config(...))->by(...))` in module providers + `throttle:<name>` routes |
| HTTP client conventions | ✅ thin wrappers + raw-body HMAC precedent | Payment `*Client` classes ("tests only ever mock this class"); inbound verification: Razorpay `hash_hmac` over **raw bytes**, Stripe SDK constructEvent, Telegram constant-time compare; `->timeout()` only in TelegramClient (15s); no `->retry(` anywhere; `Http::fake` used in Notification tests |
| Module scaffold | ✅ Audit precedent | `Modules/Audit` layout (Actions/Models/Schemas/Controllers/routes/lang×6/tests + `module.json` + `modules_statuses.json`), `Nwidart ModuleServiceProvider` with Event/Route sub-providers |
| Scheduler | ✅ live in prod | Prod compose runs `schedule:work`; per-module `registerCommandSchedules()` precedent (`order:cancel-expired` every five minutes, `sanctum:prune-expired` daily) |

### C.1 — Tax, addresses, seeds

| Fact | State | Evidence |
|---|---|---|
| Rate scoping | ❌ country-only | `tax_rates`: `country_code char(2)` + unique `(country_code, tax_class_id)`; **no region column anywhere** (grep clean across Tax/User/Order) |
| Resolution | ✅ exact-match, integer-exact | `TaxGateway::calculateForItems`: all classes keyed, rates `where(country)->where(is_active)->keyBy(class_id)`; missing/inactive rate = **valid zero**; dangling class = loud failure; coupon allocated largest-remainder BEFORE rates; shipping taxed only via the `is_shipping` class |
| Math | ✅ extended by C.1b only | `TaxMath`: `rateUnits` (regex, percent ×10⁴), `taxOf` (half-up once per line), `allocate` (largest remainder) — the exclusive-mode math is reused as-is |
| Checkout wiring | ✅ single call site | `Cart/CheckoutAction` builds `TaxCalculationInput` (items + `country_code` from the customer address + coupon + shipping) inside the checkout transaction; **tax preview reuses the same calculator** — preview/checkout parity is a property to preserve |
| Address chain | ❌ **no region field** | `user_addresses` + `order_addresses` + `AddressDto`: `full_name, phone, address_line, city, postal_code, country_code` only; storefront `address-manager.tsx` comment: the design's State select "is the backend's required country select" — a built-but-unwired assumption |
| Seed pattern | ✅ + one cautionary tale | `countries.php` flat rows → `insertOrIgnore` in `CoreDatabaseSeeder`; **`time_zones.php` is dead data** (no consumer) — the regions seed must be consumed, not just shipped |
| VAT/company fields | ❌ none | No vat/company identifier anywhere in backend or storefront |
| Tax admin surface | ✅ complete pattern to extend | Routes/controllers/actions/`TaxRateRequest` (unique per country×class)/`TaxRateResource`/`TaxTransfer::rateRules`/`tax-rate.json` i18n ×6/`tax-rates/data/schema.ts` |

## 3. Slice C.3 — Image variants

### Design decisions

| # | Decision | Why |
|---|---|---|
| D1 | **`intervention/image` v3, GD driver** — pure PHP, no system binaries. | Shared-hosting path stays viable; GD already present in CI + both Dockerfiles. |
| D2 | **Presets in `Modules/Media/config/images.php`:** `thumb 400w`, `medium 800w`, `large 1600w`, quality 82. Applied to jpeg/png/webp sources only; **gif, svg and non-images skipped**. | Deterministic, config-driven; the skip-list avoids animated-gif corruption and rasterizing vectors. |
| D3 | **Input security limits before decode** (external inputs are untrusted): read `getimagesize`-style dimensions from the stored file first; **reject decode when width or height > 8000 px or total pixels > 40 MP** (config values); treat undecodable/corrupt files as a failed generation — log + report, original still serves. Upload-size is already capped at 20 MB by `MediaRequest`. | A technically valid image can consume unbounded memory during decode; limits must precede `make()`. |
| D4 | **`GenerateImageVariantsListener`** (`ShouldQueue` + `Queueable`, auto-discovered) on `MediaCreatedEvent` → `GenerateMediaVariantsAction`. Under `QUEUE_CONNECTION=sync` (tests) it executes inline — assertions are direct. | The codebase's established async convention; zero new infrastructure. |
| D5 | **Idempotent generation, deterministic sibling paths on the SAME disk:** `{dirname}/{filename}-{preset}.{extension}`. Re-running produces the same paths; existing files are overwritten; `metadata` is **rebuilt wholesale** (never merged), so no stale entries. Generation re-checks the media row still exists before doing work (deleted-while-queued → no-op). | Running the listener twice yields identical final state (review Problem B). |
| D6 | **Per-variant status in `metadata`** (no migration): `{"width": W, "height": H, "variants": {"thumb": {"status": "completed", "path": …, "size": …, "width": …, "height": …}, "large": {"status": "failed"}}}` — a partial failure is visible and explainable; consumers use only `completed` entries. | Review Problem A: a partially generated image must remain understandable; the failed preset can be repaired by D7. |
| D7 | **Regeneration path:** artisan `media:regenerate-variants {--id=} {--all}` — re-runs generation from the original (presets are config-driven), replacing files and rebuilding `metadata`. Documented for preset changes; not exposed as an admin button in v1. | Review Problem D: preset changes must not require re-uploading originals. |
| D8 | **Exposure:** `MediaResource` gains `width/height/variants` (urls resolved per record disk, same as `url`); new `MediaGatewayInterface::getVariantsByIds(array $mediaIds): array` serves Catalog's loose `media_id` refs; `ListProductResource`/`ShowProductResource`/`PostResource`/`Category`/`Brand` payloads attach `variants` where the media row resolves — **fallback = original URL only**. | Correctness over coverage: a missing media link degrades to today's behavior. |
| D9 | **`DeleteMediaAction` enumerates all variant paths from `metadata`** and deletes them with the parent, on the record's disk. | D5's inverse; the one place variants could orphan. |
| D10 | **Storefront:** `ApiImage`/`ApiProduct`/`ApiPost` gain `variants?: {thumb?, medium?, large?}` where each carries `{url, width}`; product card renders `srcset` built from **actual intrinsic widths** (a 300-px original's thumb is 300 px wide — never claim 400) with a `sizes` attribute; gallery main = largest completed variant, thumbnails = thumb; blog card/cover likewise; JSON-LD keeps the largest/original via `absoluteAssetUrl`. Admin `media-card` uses thumb; `preview-sheet` shows dimensions + per-variant status. | Review Problem E: srcset descriptors must match reality, not the preset name. |

### Work items
- [ ] composer: `intervention/image`; `Modules/Media/config/images.php` presets + limits (D1–D3).
- [ ] `GenerateMediaVariantsAction` (limits → decode → presets → sibling writes →
      metadata rebuild with per-variant status; idempotent, re-checks the row) +
      queued listener (D4–D6).
- [ ] `media:regenerate-variants` command (D7); `DeleteMediaAction` enumeration (D9);
      `MediaResource` exposure (D8).
- [ ] `MediaGatewayInterface::getVariantsByIds()` + Catalog/Content payload attachment.
- [ ] Storefront: types + the four render sites with honest srcset (D10); admin media
      card/preview.
- [ ] Pest: both disks (mirror `MediaDiskTest`); derived paths + idempotency (run
      twice → identical state); per-variant partial-failure state; oversized/corrupt
      input rejection; gif/svg skip; delete enumeration; deleted-while-queued no-op;
      regeneration command; inline-listener invocation. Admin i18n ×6.

### Edge cases
Legacy media rows (no `media_id` linkage or missing metadata) → original URL only ·
corrupt image → `failed` status, original serves · S3 outage → listener failure logged,
original serves · media deleted while the job waits → no-op · preset widths larger than
the original → that preset is skipped, and its intrinsic width is what the storefront
receives · folder deletion → variants die with the media row via D9.

## 4. Slice C.2 — Outbound webhooks (transactional outbox) + S2S API tokens

### Design decisions

| # | Decision | Why |
|---|---|---|
| D1 | **New `Modules/Webhooks` module** (Audit scaffold: Actions/Models/Schemas/Controllers/Console/routes/lang×6/tests, `module.json`, `modules_statuses.json`). | Smallest coherent home; Audit proves the layout; keeps delivery machinery out of Order/Payment. |
| D2 | **Transactional outbox — adopted (review P0).** `webhook_events` outbox table (`event_id` uuid PK, `event_type`, `payload` JSON — the **public** payload, `created_at`, `dispatched_at` nullable). **Synchronous** catalog listeners (one per event type) insert the outbox row in the same transaction when the event fires inside one — crash-before-commit rolls back both, so no event is lost or orphaned. Events that fire after commit get the row in its own small transaction. | The reviewer's architecture: a recoverable source of truth; retries can finally recover something that was persisted. |
| D3 | **Dispatch = fast path + sweep:** after the outbox row commits, the listener also enqueues `ProcessOutboxEventJob(event_id)` (a lost enqueue is harmless); a scheduled `webhooks:dispatch-outbox` (every minute — prod compose already runs `schedule:work`) sweeps undispatched rows as the recovery path. Either path: resolve subscribed endpoints → create delivery rows → enqueue one `DeliverWebhookJob` per delivery. | At-least-once with a bounded (≤ 1 min) recovery window, no new infrastructure. |
| D4 | **Stable identifiers, two levels (review §6B):** `event_id` (the business event, shared by every endpoint's delivery) and `delivery_id` (per endpoint, stable across retries). `webhook_deliveries`: `event_id` FK → outbox, `endpoint_id`, **unique `(endpoint_id, event_id)`** (dispatch retries never duplicate deliveries), status/attempts/response fields. Headers: `X-Baxela-Event-Id` + `X-Baxela-Delivery`. | Correlation across endpoints; dedupe at the delivery-record level. |
| D5 | **Public payload schema — explicit serializers, never `event->toArray()`** (review §6A): one `WebhookPayload` builder per event type producing a documented, versioned envelope `{"api_version": "v1", "type": …, "occurred_at": …, "data": {…}}`; `data` carries ids, codes, amounts, statuses — **no customer PII beyond order codes/totals** (no addresses, emails, names). Internal event classes can change without breaking integrations. | Internal serialization is not an integration contract; PII control is a design input, not an afterthought. |
| D6 | **SSRF guard — a dedicated `UrlGuard` component** (review P0): at endpoint create/update **and re-validated at every delivery**: HTTPS-only (production), reject loopback/private/link-local/multicast/reserved ranges for IPv4 **and** IPv6, resolve DNS and validate every resolved address, **redirects disabled** (a 3xx response is a permanent failure — see D8), connection + total timeout, response body capped. Residual DNS-rebinding TOCTOU between validation and connection is documented (the HTTP client cannot pin connections without a custom handler — the re-validation-at-delivery discipline narrows it; full pinning is a documented future hardening). | The reviewer's minimum requirements; a security component with its own tests. |
| D7 | **Delivery:** `DeliverWebhookJob` (`$tries = 5` — first attempt immediate, then the four backoff intervals `[30, 120, 600, 3600]`; documented explicitly) → `WebhookDeliveryClient` with **canonical signing input `"{timestamp}.{raw_body}"`**, `X-Baxela-Signature: sha256=<hex>`, `X-Baxela-Timestamp`, `X-Baxela-Event-Id`, `X-Baxela-Delivery`; receivers verify with constant-time comparison + timestamp tolerance. Secrets: **encrypted at rest** (Laravel crypt), **displayed once** at creation/regeneration, rotation = regenerate (old secret invalid immediately, documented). | Review §D: timestamp inside the signature; replay window bounded by tolerance. |
| D8 | **Response classification (review §E):** 2xx → success · 3xx → permanent failure (redirects disabled, ties into D6) · 400/401/403 → permanent failure · 408/429/5xx/connection-timeout → retry (429 respects `Retry-After` when shorter than the backoff step) · all recorded in the delivery row. | Explicit, testable semantics. |
| D9 | **Auto-disable, precisely defined:** `consecutive_failures` increments/decrements **atomically** (`DB::increment`/`decrement` inside the delivery transaction — concurrent jobs cannot interleave the counter); **any success resets it to zero**; the endpoint disables at **10 consecutive failures** (so 9 failures + 1 success + 1 failure = 1, never disabled). | Review Problem F, including the concurrency case. |
| D10 | **Endpoints are archived, never hard-deleted** (review §6C): delete = `is_archived = true` (hidden from lists, deliveries keep their FK and history); a scheduled prune removes delivery rows older than 90 days (retention documented). | Observability history must not disappear with the endpoint. |
| D11 | **Admin surface:** endpoints CRUD (create shows the secret **once**) + `regenerate-secret` + `test` (synthetic `test` event through the real pipeline) + deliveries list (filters: endpoint/status/event) + show. Route-name permissions (`auth:sync-permissions`); i18n ×6; Bruno. | Audit/Tax CRUD pattern; deliveries are read-only. |
| D12 | **S2S tokens (machine route group) — ownership model explicit (review Problem G):** tokens are minted **only** to a designated machine/service user chosen by an admin (creation/revocation **audit-logged**); plaintext displayed **once**; abilities **explicit and default-deny** (`orders.read`, `products.read`); expiration supported (Sanctum expires_at); revocation immediate. The `api/v1/machine/...` group is **bearer-token-only** (session auth rejected) and rate-limited per token. PII note: machine tokens read the merchant's **own** store data through the owning user — `orders.read` returns that merchant's order data (including customer PII of their own customers), which is in-scope for an ERP integration and documented as such. | Review Problem G: integration identity ≠ administrative identity; granular, auditable, revocable. |

### Work items
- [ ] Scaffold `Modules/Webhooks`; migrations: `webhook_events` (outbox), `webhook_endpoints`
      (encrypted secret cast), `webhook_deliveries` (D2, D4, D10); models + schemas + enums.
- [ ] Public payload serializers per catalog event (D5); synchronous outbox listeners
      (D2); `ProcessOutboxEventJob` + `webhooks:dispatch-outbox` sweep (D3).
- [ ] `UrlGuard` (D6) + `DeliverWebhookJob` + `WebhookDeliveryClient` with canonical
      signing (D7) + response classification (D8) + atomic failure counting and
      auto-disable (D9).
- [ ] Admin endpoints CRUD + secret regeneration + test event + deliveries log (D11);
      `auth:sync-permissions`; i18n ×6; Bruno.
- [ ] S2S tokens: Auth admin minting endpoints (D12); `machine` route group + ability
      middleware + rate limiter; scope documentation.
- [ ] Pest: outbox atomicity (event-inside-transaction → row exists only after commit);
      payload serializer shape + PII absence; UrlGuard matrix (private/loopback/reserved
      IPv4+IPv6, non-HTTPS in production, rebinding re-validation); signing vector
      (canonical input, hex, constant-time); response classification table; backoff
      sequence; atomic counter + auto-disable (including the 9-1-1 case); delivery
      unique constraint; ability enforcement (403) + session rejection; rate limit;
      token lifecycle audit.

### Edge cases
Event fires inside a transaction that later rolls back → outbox row rolls back with it
(no phantom webhook) · event fires after commit → own small transaction, sweep as
backstop · endpoint archived mid-retry → pending deliveries complete, no new ones ·
delivery job for an archived endpoint → marked failed-permanent, no new attempts ·
receiver returns 429 with long Retry-After → backoff step respected as the floor.

## 5. Slice C.1a — Regional tax rates

### Design decisions

| # | Decision | Why |
|---|---|---|
| D1 | **Corrected uniqueness (review P0-1):** `region_code` is **`string(10) NOT NULL DEFAULT ''`** — the **empty string is the country-wide scope** — with unique `(country_code, region_code, tax_class_id)`. Unlike nullable-NULL uniques (which the v1 plan got backwards: multiple NULLs *allow duplicates*), a NOT NULL '' is a real value, so the database enforces exactly one country-wide rate per country × class, one rate per region × class, and many different regions — **on both MySQL and SQLite**, with a Pest test proving the duplicate country-wide insert is rejected on the test engine. | The reviewer's normalized-key recommendation in its most portable form (no generated-column support questions); '' never reaches the API — actions normalize omitted region to ''. |
| D2 | **Resolution precedence + explicit inactive semantics (review Issue 2):** for each class, the region-specific rate if present **and active**; else the country-wide rate if present **and active**; else valid zero. **Defined business rule: deactivating a regional rate removes it from consideration — the country-wide rate applies again** (deactivation means "stop using this regional rate"; the alternative — an inactive regional record blocking fallback — is rejected as an administrator trap). Documented in the gateway docblock + `docs/tax.md`. | `is_active` must mean one thing everywhere; silent tax changes must be explainable. |
| D3 | **Canonical naming: `core_regions` everywhere** (table, seed, docs — review Issue 3). Columns: `country_code char(2)`, `code char(10)` unique (ISO 3166-2 `US-CA`), `name`, timestamps. **Data source & update policy:** hand-maintained seed from ISO 3166-2 (US 51: states + DC; CA 13: provinces + territories; DE 16), updated via PRs like the countries seed; **no admin CRUD for regions in v1** (seed-only — keeps the slice scoped). | One name throughout; the update policy stated, not implied. |
| D4 | **Region flows through the address chain:** additive `region_code` on `user_addresses` + `order_addresses` (checkout snapshot), `AddressDto`, `UserGateway::getAddress`, `AddressRequest` (nullable; validated as a country+region pair by `RegionCodeRule` against `core_regions` — **an unknown region rejects the address** at validation time), and checkout passing region into the tax input. | The verified missing plumbing; the storefront design already assumed a State select. |
| D5 | **Historical vs new-checkout distinction (review Issue 4):** `order_addresses` snapshots freeze the region that was actually taxed — historical orders are never re-validated against the seed; only NEW address input is validated. | Historical data must remain valid and untouched. |
| D6 | **Tax admin + transfer:** `TaxRateRequest` (`region_code` nullable → normalized to ''), create/update actions, `TaxRateResource`, `TaxTransfer::rateRules` + section data (region joins the natural key), admin `tax-rate.json` ×6 + `tax-rates/data/schema.ts` + mutate drawer + columns. | The verified CRUD pattern, extended. |
| D7 | **Storefront:** address-manager + checkout inline form gain a region select (visible only when the chosen country has regions; options from `GET /core/public/regions?country_code=US`, cached); `ApiCountry`/`ApiAddress` types extended. | Closes the built-but-unwired design assumption. |

### Work items
- [ ] Migration (`region_code` NOT NULL DEFAULT '' + new unique — **verified on the test
      engine by a duplicate-insert rejection test**); schema/model constants.
- [ ] Core: `core_regions` migration + `regions.php` seed (US/CA/DE) + seeder + model +
      public list endpoint + `RegionCodeRule`.
- [ ] `TaxGateway` precedence + inactive semantics (D2) + `TaxCalculationInput.region_code`;
      `CheckoutAction` passes region.
- [ ] Address chain: `user_addresses`/`order_addresses` migrations, `AddressDto`,
      gateway mapping, `AddressRequest` + rule, storefront region select + types, ×6 locales.
- [ ] Tax admin surface + `TaxTransfer` + Bruno; i18n ×6.
- [ ] Pest: **the reviewer's exact matrix** — region selected + regional rate → regional;
      region selected + regional absent → country; no region → country; unknown region →
      address 422; regional inactive → country-wide (documented rule); country inactive →
      existing zero behavior; duplicate country-wide insert → unique violation; checkout
      region snapshot; transfer round-trip; admin CRUD.

### Edge cases
Country with no seeded regions → no select, '' region, unchanged behavior · seed drift
(address region no longer in `core_regions`) → new checkouts reject the address;
historical order snapshots remain valid and untouched (D5) · multiple regions per class
coexist with the single country-wide rate.

## 6. Slice C.1b — Tax-inclusive pricing (store-level mode)

### Design decisions

| # | Decision | Why |
|---|---|---|
| D1 | **Store-level pricing mode (review P0-4/-5, Option 1): `prices_include_tax` is a Setting** (admin settings UI, i18n ×6) — the merchant's pricing policy, explicit and store-wide. **The per-rate `price_contains_tax` flag is dropped.** | Tax jurisdiction and stored price semantics are different concepts; one store-level mode makes the order/invoice snapshot unambiguous. |
| D2 | **The mode is an input to the tax calculation:** `TaxCalculationInput` gains `bool prices_include_tax` (resolved from the setting by `CheckoutAction` **and** the tax preview — preview/checkout parity preserved, review §6D). Inclusive → tax **extracted** from the base; exclusive → today's `taxOf` path unchanged. | The calculator stays the single decision point both flows share. |
| D3 | **Extraction math in `TaxMath` (integer-exact):** net = ⌊base × 10⁶ ÷ (10⁶ + rateUnits)⌋; **tax = base − net** (the fractional remainder lands in the tax — deliberate: exclusive mode rounds tax half-up, inclusive mode floors the net so the tax absorbs the remainder; the asymmetry is justified and pinned by test vectors asserting net AND tax, not just their sum). Overflow re-checked (base ≤ 10¹² keeps base×10⁶ < PHP_INT_MAX). | Review Problem C: a different rounding policy is fine when justified and tested on both outputs. |
| D4 | **Snapshots:** orders gain `prices_contain_tax` (bool, **migration default false** — the historical exclusive model, documented); invoices copy the flag from the order at issuance (B.1 snapshot discipline — historical invoices never recalculate). Per-line snapshots keep the extracted `tax_amount`/`tax_rate`; **mixed inclusive/exclusive lines cannot occur** (store-wide mode — the v1 edge case is removed). | Review Problem B resolved by construction; Problem D's migration default documented. |
| D5 | **Storefront + preview:** tax preview and PDP price block surface an "incl. VAT"-style marker (i18n ×6) when the store mode is inclusive; the displayed price remains the gross figure the customer pays. | Display parity without per-currency pricing. |

### Work items
- [ ] Setting: `SettingNameEnum::PRICES_INCLUDE_TAX` (default false) + admin settings UI
      + i18n ×6.
- [ ] `TaxMath` extraction + `TaxGateway` mode path + `TaxCalculationInput`/
      `TaxResult` mode flag; `CheckoutAction` resolves the setting and snapshots it.
- [ ] Orders + `order_invoices` additive `prices_contain_tax` columns (default false);
      B.1 kernel schemas updated.
- [ ] Tax preview marker + storefront i18n ×6; Bruno; transfer rules.
- [ ] Pest: extraction rounding vectors (net AND tax asserted — fractional minor-unit
      cases); discounts before extraction; shipping inclusive through its class rate;
      multiple quantities/classes; zero-rated; checkout + invoice flag snapshot;
      historical default false; preview/checkout parity.

### Edge cases
Mode flip mid-life → historical orders/invoices keep their frozen figures (policy 4:
money is reproducible) · zero-rate + inclusive → tax 0, flag still snapshots the mode ·
exclusive mode → bit-for-bit today's behavior.

## 7. Slice C.1c — VAT ID collection + offline validation (v1 scope)

> **Scope correction (review P0-6/-7, adopted):** v1 ships **VAT collection + offline
> format validation + evidence snapshotting**. It does **not** implement automatic
> reverse charge — format validity is not registration verification, and a
> billing-vs-store-country comparison is not a jurisdiction model. Automatic reverse
> charge defers until a verification policy exists (a VIES extension, or a manual
> admin verification flow with an audit trail). The eligibility seam is documented:
> `TaxGatewayInterface` is where a future `reverseChargeEligible(...)` decision joins —
> the calculator's line-level application point already exists for it.

### Design decisions

| # | Decision | Why |
|---|---|---|
| D1 | **`vat_id` (string, nullable) on `user_addresses` + `order_addresses` (snapshot) + `AddressDto`; collected in the storefront address forms, labeled for business customers.** Billing address governs the submitted VAT ID; shipping addresses never override it; the order snapshots the actually submitted identity; changing a customer's VAT ID later never rewrites historical orders. | Review Problem D: collection at the address is fine; the canonical business identity entity is a documented future abstraction. |
| D2 | **Offline validation only:** `VatIdRule` + a `Tax`-module format validator (per-country regex, EU set first — the storefront locales' countries; normalized input: trimmed, uppercase, spaces removed). Exposed via `TaxGatewayInterface::validateVatId(country, vat): bool`. **The validator's output means "plausible format", nothing more** — the docblock states it is not registration verification. | Review Problem A: the two concepts are separated in both code and wording. |
| D3 | **No automatic tax zeroing in v1.** The tax calculation is untouched by this slice; the VAT ID is collected, validated, and frozen as evidence for the future eligibility decision (and for merchant bookkeeping/display). | Review verdict: approve collection + validation; reject format-based automatic reverse charge. |
| D4 | **Evidence frozen on the order:** additive `buyer_vat_id` (nullable) on orders, persisted by `CheckoutAction` from the billing address. B.1 Phase 2's invoice `buyer_identity` copies the VAT ID **from the order snapshot at invoice creation** — never dynamically from the customer's current address (review Problem E). | Snapshot discipline; a later VAT registration change cannot alter an old invoice. |

### Work items
- [ ] `user_addresses`/`order_addresses` migrations + schema constants + `AddressDto` +
      gateway mapping + `AddressRequest` rule.
- [ ] `VatIdRule` + `Tax`-module format validator + `TaxGatewayInterface::
      validateVatId()` + implementation (normalized input; per-country regex table).
- [ ] Orders additive `buyer_vat_id` + `CheckoutAction` persistence; store-address forms
      (conditional VAT input per country, i18n ×6); types; Bruno; transfer rules.
- [ ] Pest: format matrix per country (valid/invalid/normalized); snapshot on order;
      billing-governs rule (shipping VAT ignored); historical immutability; preview
      unaffected.

### Edge cases
VAT ID on shipping but not billing → billing governs · unknown country code → format
check skipped (no rule), stored as submitted, flagged as unvalidated · future reverse
charge → joins at the documented `TaxGatewayInterface` seam with its own verification
policy slice.

## 8. Cross-cutting verification & policies

1. **Per slice:** scoped Pest during work; full backend suite + phpstan neutral
   (baseline-splice pattern for new test files) + Pint once per slice; storefront/admin
   builds when those apps change; i18n parity ×6 whenever admin surfaces change;
   live-compose spot check at slice end (dev stack + Bruno).
2. **Schema discipline:** every migration additive; no destructive changes (the
   marketplace roadmap §5.2 clock is running).
3. **Policies this phase writes down:**
   (a) *catalogs are events, endpoints are subscriptions* — new events join the webhook
   catalog by adding one map entry;
   (b) *tax configuration failures are loud* — the TaxGateway exception philosophy
   extends to every new flag and input;
   (c) *unavailable data is omitted, never fabricated* (B.2's rule, restated for image
   variants and webhook payloads);
   (d) *money calculations are reproducible* — historical orders, invoices and refunds
   never depend on current tax rates, region seeds, or store settings;
   (e) *external inputs are untrusted* — image uploads, webhook URLs, VAT identifiers
   and machine API requests are validated at their respective boundaries.
4. **Suggested commits:** one per phase within each slice — e.g.
   `feat(backend/media): queued idempotent image variant generation`,
   `feat(storefront): responsive image variants`,
   `feat(backend/webhooks): transactional outbox and signed webhook delivery`,
   `feat(backend/auth): scoped s2s tokens and machine api`,
   `feat(backend/tax): region-scoped tax rates`,
   `feat(backend/tax): store-level tax-inclusive pricing`,
   `feat(backend/tax): vat id collection and offline validation`.

## 9. Explicit non-goals (restated; silence reads as oversight)

- **Automatic reverse charge** — deferred until a verification policy exists (VIES
  extension or manual admin verification with audit trail); the eligibility seam is
  documented, the behavior is not pretended.
- **AvaTax / TaxJar** — future `tax-engine` extensions; core does regions + a
  store-level pricing mode + offline VAT format validation well.
- **Carrier APIs / labels / live tracking** — the `shipping-carrier` extension type.
- **Multi-warehouse / stock reservations** — unchanged single-stock model.
- **Per-currency price lists** — display conversion stays display-only (B.2 D5 rule).
- **Webhook replay UI, event transformations, per-endpoint payload templates, image
  format conversion (webp/avif outputs)** — v1 keeps fixed versioned payloads and
  source-format variants; these are deliberate extension territory.
- **E-invoicing formats** (Factur-X, Moadian) — extensions (gap roadmap §5).
- **Region admin CRUD** — seed-only in v1 (D3); admin management follows demand.

## 10. Review trail

- v1 (2026-11-07): plan authored from three parallel code explorations; owner
  confirmed seed scope (US+CA+DE), execution order (C.3 → C.2 → C.1a → C.1b → C.1c),
  and the machine-route-group token design.
- v2 (2026-11-07): external review (7.5/10 — "request one more revision") incorporated:
  **P0-1** C.1a uniqueness corrected (nullable-NULL semantics were backwards →
  NOT NULL DEFAULT '' scope key + dual-engine proof test); **P0-2** transactional outbox
  adopted (`webhook_events` + sync listeners + sweep; the v1 reconciliation-only
  approach replaced); **P0-3** SSRF `UrlGuard` added (validation at create AND delivery,
  redirects disabled via the 3xx classification, rebinding residual documented);
  **P0-4/-5** C.1b moved to a store-level `prices_include_tax` setting (per-rate flag
  dropped; the mixed-classes contradiction dissolved by construction);
  **P0-6/-7** C.1c rescoped to collection + offline validation + evidence (automatic
  reverse charge and blanket zeroing removed; eligibility seam documented). **P1:**
  C.3 idempotency/per-variant status/security limits/regeneration/srcset widths;
  C.2 event_id vs delivery_id + unique (endpoint, event) + explicit public payload
  serializers with versioning + canonical signing input (timestamp.body) + secret
  encryption/display-once/rotation + response classification + atomic auto-disable
  semantics + archive-not-delete + delivery retention + token ownership model;
  C.1a inactive-rate rule + `core_regions` naming + source policy + historical/new
  distinction; C.1b rounding justification + migration defaults; C.1c invoice snapshot
  timing; policies (d) and (e) added; estimates restated as initial estimates.

## 11. Related docs

- `docs/PLATFORM_GAPS_ROADMAP.md` §Phase C — the seed section this plan elaborates;
  §1 placement table and §6 policies bind the slices.
- `docs/plans/gap-b1-invoices.md` — `RefundFinalizedEvent` (Phase 3) and invoice
  `buyer_identity` (Phase 2) are the two C.2/C.1c handoffs.
- `docs/EXTENSION_MARKETPLACE_ROADMAP.md` §5 — additive-migrations + semver-surface
  policies binding all schema changes above.
- `apps/backend/docs/flows/order-state-machine.md` — where tax snapshots sit in the
  lifecycle.
