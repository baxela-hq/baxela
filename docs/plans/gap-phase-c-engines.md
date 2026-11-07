# Phase C — Engine work: tax engine, outbound webhooks + tokens, image variants (v3)

> **Purpose of this document:** Self-contained execution plan for **Phase C** of
> `docs/PLATFORM_GAPS_ROADMAP.md` — the three engine workstreams, split into five
> implementation slices. Like gap-b1/gap-b2, this document stands alone: verified
> state, design decisions, build order, edge cases and tests — readable without
> conversation history and handable to an external review.
>
> **Created:** 2026-11-07 · **Owner:** solo maintainer · **Status:** v3 — the second
> external review (8.5/10: "good architecture, request one more focused revision")
> is incorporated; see the review trail (§10) for the verdict-per-slice table and
> where every correction landed. Facts verified 2026-11-07 by three parallel code
> explorations.
>
> **Execution order (owner-confirmed, unchanged):** **C.3 → C.2 → C.1a → C.1b →
> C.1c**. Slices are strictly sequential: each lands green and committed before the
> next starts. **Size estimates are initial estimates, not release commitments**
> (the reviewer's caution is adopted verbatim): C.3 ≈ 2 d, C.2 ≈ 4–5 d, C.1a ≈ 2–3 d,
> C.1b ≈ 2 d, C.1c ≈ 1 d.
>
> **How to use:** Re-verify ⚠-marked facts before relying on them. Conventional
> commits; never commit unless asked. Heavy commands `nice -n 10`. Tests scoped per
> module during work, full suite once per slice.

---

## 1. Where this fits

| Slice | Content | Impl. order | Target release | Why then |
|---|---|---|---|---|
| C.3 | Image variants (queued, idempotent, concurrency-safe) | 1st | v0.2 | Smallest, fully independent of checkout; immediate LCP win |
| C.2 | Outbound webhooks (outbox + claims) + S2S API tokens | 2nd | v0.3 | Unlocks ERP/mobile integrations early; builds on the existing event system only |
| C.1a | Regional tax rates (ISO 3166-2) | 3rd | v0.2–v0.3 | The strategic gap; touches checkout + addresses |
| C.1b | Tax-inclusive pricing (store-level mode, full accounting model) | 4th | v0.3 | Builds directly on C.1a's engine work |
| C.1c | VAT ID collection + offline validation | 5th | v0.3 | Completes VAT collection; automatic reverse charge defers to a verification policy (§7) |

**Clarification (review §3-A):** "Target release" is where the feature becomes
**available** (release availability); the "Impl. order" column is when it is
**built**. They intentionally differ — C.1a ships in v0.2–v0.3 even though it is
built after C.2 (which targets v0.3).

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
| Upload limit | ✅ 20 MB at the request layer | `MediaRequest`: `File::types(...)->max('20mb')` — dimensions/pixel/memory limits still needed inside the variant generator |
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
| Sanctum | ⚠ abilities never used; **session hybrid possible** | Tokens always `['*']`; no ability middleware wired; no token-management surface; `auth:sanctum` can authenticate first-party **session cookies** too — machine routes need explicit bearer-token enforcement (review §2-F) |
| Rate limiting | ✅ pattern established | `RateLimiter::for('<name>', fn (Request $r) => Limit::perMinute(config(...))->by(...))` in module providers + `throttle:<name>` routes |
| HTTP client conventions | ✅ thin wrappers + raw-body HMAC precedent | Payment `*Client` classes ("tests only ever mock this class"); inbound verification: Razorpay `hash_hmac` over **raw bytes**, Stripe SDK constructEvent, Telegram constant-time compare; `->timeout()` only in TelegramClient (15s); `Http::withOptions(['curl' => …])` is available for cURL-level control |
| Module scaffold | ✅ Audit precedent | `Modules/Audit` layout + `module.json` + `modules_statuses.json`, `Nwidart ModuleServiceProvider` with Event/Route sub-providers |
| Scheduler | ✅ live in prod | Prod compose runs `schedule:work`; per-module `registerCommandSchedules()` precedent (`order:cancel-expired` every five minutes, `sanctum:prune-expired` daily) |

### C.1 — Tax, addresses, seeds

| Fact | State | Evidence |
|---|---|---|
| Rate scoping | ❌ country-only | `tax_rates`: `country_code char(2)` + unique `(country_code, tax_class_id)`; **no region column anywhere** (grep clean across Tax/User/Order) |
| Resolution | ✅ exact-match, integer-exact | `TaxGateway::calculateForItems`: all classes keyed, rates `where(country)->where(is_active)->keyBy(class_id)`; missing/inactive rate = **valid zero**; dangling class = loud failure; coupon allocated largest-remainder BEFORE rates; shipping taxed only via the `is_shipping` class |
| Accounting model | ✅ exclusive-only today | `Total = Subtotal − Discount + Shipping + Tax`; C.1b must define the inclusive counterpart (§6) without re-adding embedded tax |
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
| D3 | **Input security limits before decode** (external inputs are untrusted): read dimensions from the stored file first; reject decode when width or height > 8000 px or total pixels > **`max_pixels` (config, default 20 MP — memory-aware, not assumed-safe: GD decodes at ~4.6 bytes/px plus intermediate copies, and jobs may run concurrently)**; treat undecodable/corrupt files as a **permanent** failure — log + report, original still serves. Upload-size is already capped at 20 MB by `MediaRequest`. | A technically valid image can consume unbounded memory during decode; limits must precede `make()`, and the pixel budget must respect PHP memory limits. |
| D4 | **Failure classification (review P1-C):** corrupt/undecodable source or unsupported type → **permanent** (variant marked failed, no retry); storage/S3 outage → **retryable** (listener re-entrant, nothing marked permanently failed); individual preset encode failure → **partial** (other presets stand, the failed one is repairable by regeneration). | A temporary storage problem must never be recorded as a permanently failed variant. |
| D5 | **Idempotent generation, versioned sibling paths on the SAME disk:** `{dirname}/{filename}-{preset}-v{N}.{extension}` where `v{N}` is the generation version recorded in `metadata`. Re-running with the same version overwrites deterministically and rebuilds `metadata` wholesale; **regeneration bumps `N`**, writes the new set, then deletes the previous version's files — new URLs invalidate browser/CDN caches naturally (review P1-A). Originals untouched. | Deterministic paths AND cache-safe regeneration, without re-uploading originals. |
| D6 | **Concurrency-safe (review P0-5):** a **per-media atomic lock** (`Cache::lock("media-variants:{id}")`, TTL 5 min, non-blocking acquire) wraps generation **and** deletion — `DeleteMediaAction` acquires the same lock, so generate-vs-delete and concurrent double-generation cannot interleave; a job that cannot acquire the lock re-releases itself (delay 60s); generation re-checks the media row **inside** the lock (deleted-while-queued → no-op); lock loss/TTL expiry → the sweep or a retry regenerates idempotently. | Sequential idempotency alone does not survive concurrent workers; the lock serializes the per-media lifecycle. |
| D7 | **Per-variant status in `metadata`** (no migration): `{"version": N, "width": W, "height": H, "variants": {"thumb": {"status": "completed", "path": …, "width": …}, "large": {"status": "failed", "reason": …}}}` — partial failures are visible; consumers use only `completed` entries; permanent vs retryable is distinguishable (D4). | A partially generated image remains understandable; repair is explicit. |
| D8 | **Regeneration path:** artisan `media:regenerate-variants {--id=} {--all}` — bumps the version, re-runs generation from the original, prunes old-version files. Documented for preset changes; not an admin button in v1. | Preset changes must not require re-uploading originals. |
| D9 | **Exposure:** `MediaResource` gains `width/height/variants`; new `MediaGatewayInterface::getVariantsByIds(array $mediaIds): array` serves Catalog's loose `media_id` refs; public payloads attach `variants` where the media row resolves — **only `completed` variants are ever advertised; failed/missing entries fall back to the original URL** (asserted by serialization tests). | Correctness over coverage; incomplete variants are never advertised as usable. |
| D10 | **`DeleteMediaAction` enumerates all variant paths for the current version (and any prior versions still on disk) under the D6 lock.** | The one place variants could orphan. |
| D11 | **Storefront:** `ApiImage`/`ApiProduct`/`ApiPost` gain `variants?: {thumb?, medium?, large?}` each carrying `{url, width}`; product card renders `srcset` from **actual intrinsic widths** (a 300-px original's thumb is 300 px — never claim 400) with a `sizes` attribute; gallery main = largest completed variant, thumbnails = thumb; blog card/cover likewise; JSON-LD keeps the largest/original. Admin `media-card` uses thumb; `preview-sheet` shows dimensions + per-variant status. | srcset descriptors must match reality, not the preset name. |

### Work items
- [ ] composer: `intervention/image`; `Modules/Media/config/images.php` presets + limits (D1–D3).
- [ ] `GenerateMediaVariantsAction` (limits → classification → presets → versioned sibling
      writes → metadata rebuild; lock-guarded, row re-check inside the lock) + queued
      listener (D4–D7).
- [ ] `media:regenerate-variants` command (D8); `DeleteMediaAction` under lock (D10);
      `MediaResource` exposure (D9).
- [ ] `MediaGatewayInterface::getVariantsByIds()` + Catalog/Content payload attachment.
- [ ] Storefront: types + the four render sites with honest srcset (D11); admin media
      card/preview.
- [ ] Pest: both disks (mirror `MediaDiskTest`); idempotency (run twice → identical
      state) **and concurrency** (lock excludes a second worker); versioned-path
      cache-busting on regeneration; per-variant partial-failure state; permanent vs
      retryable classification; oversized/corrupt rejection; gif/svg skip; delete
      enumeration under lock; deleted-while-queued no-op; regeneration command; only-
      completed-variants serialization. Admin i18n ×6.

### Edge cases
Legacy media rows (no `media_id` linkage or missing metadata) → original URL only ·
corrupt image → permanent `failed` status, original serves · S3 outage → retryable,
nothing marked permanent · media deleted while the job waits → no-op under lock ·
preset widths larger than the original → preset skipped, intrinsic width advertised ·
two regeneration runs with different presets → newest version wins, old files pruned.

## 4. Slice C.2 — Outbound webhooks (transactional outbox + claim-based delivery) + S2S API tokens

### Design decisions

| # | Decision | Why |
|---|---|---|
| D1 | **New `Modules/Webhooks` module** (Audit scaffold + a `Jobs/` dir — justified below). | Smallest coherent home; Audit proves the layout. |
| D2 | **Transactional outbox with a durable source-event identity:** `webhook_events` (`event_id` uuid PK, **`source_key` string unique** — the deterministic business identity, e.g. `order.paid:1024:{payment_id}`, derived per event type; a repeated listener invocation for the same business event **insert-or-ignores** instead of creating a second outbox row), `event_type`, `payload` JSON (the **public** payload), `created_at`, `processed_at` nullable. **Synchronous** catalog listeners insert-or-ignore in the firing transaction (crash-before-commit rolls back event + outbox atomically). | Review P0-2's identity requirement: a fresh UUID per invocation deduplicates nothing; `source_key` is the stable business identity. |
| D3 | **Two separated responsibilities (review P0-2):** **(A) Outbox processing** — `ProcessOutboxEventJob(event_id)`: resolves subscribed endpoints (subscription evaluation happens **here**, at delivery-creation time — deterministic single point), creates delivery rows, marks `processed_at` — all in one transaction, guarded by the `(endpoint_id, event_id)` unique. **(B) Delivery scheduling** — the **delivery table is the durable source of truth** for pending work: a scheduled `webhooks:dispatch-deliveries` (every 5 min) finds claimable deliveries and enqueues their jobs. Fast path: processing also enqueues delivery jobs (a lost enqueue is harmless — the sweep recovers). | A crash between "processed" and "enqueued" can no longer lose deliveries: the delivery rows exist and the sweep claims them. |
| D4 | **Claim-based delivery lifecycle (review P0-2):** `webhook_deliveries.status ∈ pending → processing → succeeded | failed_retryable | failed_permanent`, with `claimed_at`/`claimed_by` as a **lease**: `DeliverWebhookJob` claims atomically (`UPDATE … SET status='processing', claimed_at=now() WHERE id=? AND status IN ('pending','failed_retryable')` — exactly one worker wins), so **no two jobs ever HTTP-deliver the same record concurrently**; a lease older than 10 minutes is reclaimable by the sweep (crashed worker recovery); every attempt appends to the delivery's attempt history. | The unique constraint prevents duplicate *records*, not duplicate *HTTP requests*; the lease closes that gap. |
| D5 | **Stable identifiers, two levels:** `event_id` (shared by all of an event's deliveries) and `delivery_id` (per endpoint, stable across retries); headers `X-Baxela-Event-Id` + `X-Baxela-Delivery`. | Correlation across endpoints; dedupe at the delivery-record level. |
| D6 | **Public payload schema — explicit serializers, never `event->toArray()`:** one `WebhookPayload` builder per event type producing the documented, versioned envelope `{"api_version": "v1", "type": …, "occurred_at": …, "data": {…}}`; `data` carries ids, codes, amounts, statuses — **no customer PII** (no addresses, emails, names) and **no VAT IDs**. Internal event classes can change without breaking integrations. | Internal serialization is not an integration contract. |
| D7 | **SSRF guard with IP-pinned connections (review P0-3):** a dedicated `UrlGuard` validates at endpoint create/update **and resolves at every delivery**: HTTPS-only (production), reject userinfo/credentials in URLs, non-HTTP(S) ports (allow-list, default 443), malformed hosts, and non-canonical IP-literal encodings; resolve DNS (with a resolution timeout) and validate **all A and AAAA** results — public only, **IPv4-mapped IPv6 (`::ffff:0:0/96`) and unusual representations normalized before checks**; then **pin the connection** via cURL `CURLOPT_RESOLVE` (`host:port:validated-ip`, rotated across retry attempts) so the HTTP client connects to the validated address while TLS hostname verification stays intact — the rebind race is closed, not merely narrowed. Redirects disabled (3xx = permanent failure). Connection/total timeouts + response-size cap. An outbound network policy where infrastructure permits is documented as defense in depth. | The reviewer's preferred mechanism is implementable through Guzzle cURL options; the feature may then be described as SSRF-pinned, not merely SSRF-checked. |
| D8 | **Canonical signing input — metadata is authenticated (review P1-A):** `signed_payload = "{timestamp}.{event_id}.{delivery_id}.{raw_body}"`, `X-Baxela-Signature: sha256=<hex(HMAC_SHA256(secret, signed_payload))>`; `X-Baxela-Timestamp` carries the timestamp; receivers verify constant-time + timestamp tolerance (config, default 5 min); the documented receiver-side algorithm ships with a **worked test vector**. Secrets: **encrypted at rest** (Laravel crypt), **displayed once**, rotation = regenerate (old secret invalid immediately, documented). | Replay and metadata-tampering are both covered by the signature. |
| D9 | **Response classification (explicit):** 2xx → success · 3xx → permanent failure (redirects disabled) · 400/401/403 → permanent failure · 408/429/5xx/connection-timeout → retryable. **Delay = max(backoff step, Retry-After), capped at 24 h; invalid/negative Retry-After ignored** (resolves the v2 D8/edge-case contradiction). `$tries = 5`: first attempt immediate + four backoff steps `[30, 120, 600, 3600]` (documented); Laravel job attempts and persisted delivery attempts are synchronized by incrementing the delivery row inside the job itself (the job is the only writer). | Explicit, testable semantics; the reviewer's table verbatim. |
| D10 | **Auto-disable, precisely defined:** `consecutive_failures` is updated **inside an endpoint-level lock** when a delivery reaches a terminal state (success resets to zero; failure increments), so concurrent completions apply in a defined order; the endpoint disables at **10 consecutive failures** (9 failures + 1 success + 1 failure = 1). | Review Problem F, including the concurrency case. |
| D11 | **Archival semantics (review §3-B, contradiction resolved):** deleting an endpoint **archives** it (`is_archived`, hidden from lists, never hard-deleted); an **in-flight (processing) delivery completes**; a **pending, not-yet-claimed delivery is cancelled** (`failed_permanent`, reason `endpoint_archived`); **no new deliveries** are created for archived endpoints. Scheduled prune: deliveries older than **90 days** and outbox events older than 90 days **whose deliveries are all terminal** are removed (FK: deliveries reference the outbox event; retention deletes events only after their deliveries are gone). | Deterministic lifecycle; observability history retained with a stated policy. |
| D12 | **Admin surface:** endpoints CRUD (secret shown **once**) + `regenerate-secret` + `test` (synthetic `test` event through the real pipeline) + deliveries list (filters: endpoint/status/event) + show. Route-name permissions; i18n ×6; Bruno. | Audit/Tax CRUD pattern; deliveries are read-only. |
| D13 | **S2S tokens — ownership + enforcement explicit:** tokens are minted **only** to a designated machine/service user chosen by an admin; creation/revocation **audit-logged**; plaintext shown **once**; abilities **explicit and default-deny** (`orders.read`, `products.read`); expiration supported; revocation immediate. The `api/v1/machine/...` group is read-only (orders list/show, products list/show reusing existing actions), rate-limited per token, and guarded by a dedicated middleware that **requires a bearer personal-access token and explicitly rejects session-authenticated requests** (`$request->bearerToken()` present and `user()->currentAccessToken()` instanceof the Sanctum token) **plus an ability check on every route**. PII note: machine tokens read the merchant's **own** store data through the owning user — documented as in-scope for an ERP integration. | Review §2-F: `auth:sanctum` alone does not guarantee bearer-only; the middleware closes the session hybrid. |

### Work items
- [ ] Scaffold `Modules/Webhooks`; migrations: `webhook_events` (outbox, source_key
      unique), `webhook_endpoints` (encrypted secret, is_archived),
      `webhook_deliveries` (status enum, lease, unique `(endpoint_id, event_id)`);
      models + schemas.
- [ ] Public payload serializers (D6); synchronous outbox listeners with `source_key`
      (D2); `ProcessOutboxEventJob` (D3); `webhooks:dispatch-deliveries` sweep (D3).
- [ ] `UrlGuard` with DNS resolution + IP classification + `CURLOPT_RESOLVE` pinning
      (D7); `DeliverWebhookJob` with atomic claim/lease (D4) + canonical signing (D8) +
      classification (D9) + endpoint lock and auto-disable (D10).
- [ ] Archival semantics + retention commands (D11); admin CRUD + secret regeneration +
      test event + deliveries log (D12); `auth:sync-permissions`; i18n ×6; Bruno.
- [ ] S2S tokens: Auth admin minting endpoints (D13); `machine` route group + the
      bearer-only middleware + ability checks + per-token rate limiter; scope docs.
- [ ] Pest: outbox atomicity + `source_key` dedupe; payload serializer shape + PII/VAT
      absence; UrlGuard matrix (private/loopback/reserved/mapped-IPv6, non-HTTPS,
      credentials, odd ports, DNS-rebind pinned-connection test with `Http::fake`
      + a curl-level integration check ⚠); signing vector; claim exclusivity (two
      workers, one HTTP call); lease expiry reclaim; response classification; backoff
      + Retry-After max rule; atomic counter + auto-disable (9-1-1 case); archival
      lifecycle (in-flight completes / pending cancelled / new prohibited); retention;
      ability enforcement + **session rejection**; rate limit; token lifecycle audit.

### Edge cases
Event fires inside a transaction that rolls back → outbox row rolls back too (no
phantom webhook) · duplicate listener invocation → `source_key` insert-or-ignore keeps
one event · processed event whose enqueue was lost → sweep claims the pending
deliveries · receiver 429 with huge Retry-After → capped at 24 h · receiver 429 with
invalid Retry-After → backoff step applies · endpoint archived mid-flight → in-flight
completes, pending cancelled, none new · endpoint deleted by mistake → archive
restores.

## 5. Slice C.1a — Regional tax rates

### Design decisions

| # | Decision | Why |
|---|---|---|
| D1 | **Uniqueness via a NOT NULL scope key:** `region_code` is **`string(10) NOT NULL DEFAULT ''`** — the **empty string is the country-wide scope** — with unique `(country_code, region_code, tax_class_id)`. A real value (unlike NULL, which v1 got backwards) makes the database enforce: one country-wide rate per country × class, one rate per region × class, many regions — **on both MySQL and SQLite**, with a Pest test proving the duplicate country-wide insert is rejected on the test engine. | The reviewer's normalized-key recommendation in its most portable form; '' never reaches the API — actions normalize omitted region to ''. |
| D2 | **Resolution precedence + explicit inactive semantics:** for each class, the region-specific rate if present **and active**; else the country-wide rate if present **and active**; else valid zero. **Deactivating a regional rate removes it from consideration — the country-wide rate applies again** (documented in the gateway docblock + `docs/tax.md`; the blocking alternative is rejected as an administrator trap). | `is_active` means one thing everywhere. |
| D3 | **Missing-region loudness (review P0-4) — the zero-tax ambiguity resolved with four explicit rules:** (1) **no rates configured for the country at all** → valid zero (the existing documented intentional behavior); (2) **the country has regional rates configured, but the checkout address lacks a region** → **loud failure** (`TaxConfigurationException('tax.configuration.region_required')` aborting checkout — the same philosophy as the missing-default-class failure; the storefront surfaces a region-required state on the address form); (3) a regional rate is inactive → country-wide fallback; (4) an unknown region → rejected at address validation. | Under-collection by silent zero is exactly what the "loud failures" policy forbids; rule 2 closes the reviewer's California hole. |
| D4 | **Jurisdiction address is explicit (review P0-4):** the tax jurisdiction is the **shipping destination** (physical goods); billing is used only when no shipping address exists. `CheckoutAction` and the tax preview both resolve the address the same way — preview/checkout parity preserved. | Not left to implementation interpretation. |
| D5 | **Limited regional model, documented:** one regional rate per region × class; **no county/city/special-district layers, no Canadian tax-component splits** — ISO 3166-2 is the scope foundation, not a complete jurisdiction engine (review P1-A). | v1 honesty about coverage. |
| D6 | **Canonical region-code representation `US-CA` / `CA-ON` / `DE-BY` everywhere** — address validation, tax-rate administration, API responses, tax resolution — via one normalizer helper (review P1-B). **Canonical naming: `core_regions` throughout.** Data source & update policy: hand-maintained seed from ISO 3166-2 (US 51, CA 13, DE 16), updated via PRs; **no admin CRUD for regions in v1**. | One representation, one name, a stated update policy. |
| D7 | **Region flows through the address chain:** additive `region_code` on `user_addresses` + `order_addresses` (checkout snapshot), `AddressDto`, `UserGateway::getAddress`, `AddressRequest` (nullable, pair-validated by `RegionCodeRule`), checkout passing region into the tax input. Storefront: address-manager + checkout inline form gain a region select (visible only when the country has regions; options from `GET /core/public/regions?country_code=…`), types ×6 locales. | The verified missing plumbing. |
| D8 | **Migration is a schema alteration, not purely additive (review P1-C):** the old unique `(country_code, tax_class_id)` is replaced by `(country_code, region_code, tax_class_id)` — the pre-existing index guarantees no duplicates exist before the change (dedup check included in the migration for safety), rollback restores the old index (documented: rollback drops region-specific rates, acceptable pre-v0.3); **migration tested against seeded data on MySQL and SQLite**. Tax admin + transfer: `TaxRateRequest` (region nullable → normalized ''), actions, `TaxRateResource`, `TaxTransfer::rateRules` + section data, admin `tax-rate.json` ×6 + `schema.ts` + drawer + columns. | The reviewer's migration checklist, explicitly. |

### Work items
- [ ] Migration (dedup-safe unique replacement, both engines) + schema/model constants.
- [ ] Core: `core_regions` migration + `regions.php` seed (US/CA/DE) + seeder + model +
      public list endpoint + `RegionCodeRule` + the region normalizer helper.
- [ ] `TaxGateway` precedence + inactive rule + **region-required loud failure** (D2–D3)
      + `TaxCalculationInput.region_code`; `CheckoutAction` resolves the shipping
      destination (D4) and passes region.
- [ ] Address chain: `user_addresses`/`order_addresses` migrations, `AddressDto`,
      gateway mapping, `AddressRequest` + rule, storefront region select + types, ×6 locales.
- [ ] Tax admin surface + `TaxTransfer` + Bruno; i18n ×6.
- [ ] Pest: the reviewer's matrix — region + regional rate → regional; region + absent
      regional → country; no region → **country if none configured, else loud
      region-required failure**; unknown region → address 422; regional inactive →
      country; country inactive → zero; duplicate country-wide insert → unique
      violation; jurisdiction = shipping destination (billing only as fallback);
      migration against seeded data (both engines ⚠); checkout region snapshot;
      transfer round-trip; admin CRUD.

### Edge cases
Country with no seeded regions → no select, '' region, unchanged behavior · seed drift
→ new checkouts reject the address; historical `order_addresses` snapshots stay valid
and untouched (frozen region = what was actually taxed) · multiple regions per class
coexist with the single country-wide rate.

## 6. Slice C.1b — Tax-inclusive pricing (store-level mode, full accounting model)

### Design decisions

| # | Decision | Why |
|---|---|---|
| D1 | **Store-level pricing mode (owner + review): `prices_include_tax` is a Setting** (admin settings UI, i18n ×6) — the merchant's pricing policy, store-wide. The per-rate flag stays dropped. | Jurisdiction and stored price semantics are different concepts; one mode makes every snapshot unambiguous. |
| D2 | **The complete accounting model (review P0-1) — column meanings per mode.** The customer's payable (gross) is authoritative in both modes; the extracted tax is **informational in inclusive mode and never added again**: | The v2 formula alone would have double-charged (110 + 10); the model below is what makes inclusive mode correct. |
| | • **Exclusive:** `total = subtotal − discount + shipping + tax` (today's model, unchanged). | |
| | • **Inclusive:** `total = subtotal − discount + shipping` — subtotal/shipping/discount stay **gross** (what the price tags say); `tax_amount` = the extracted portion **already inside** those figures. | |
| | • Per line: `gross = net + tax` (extraction: net = ⌊gross × 10⁶ ÷ (10⁶ + rateUnits)⌋, **tax = gross − net** — the remainder lands in the tax; **extraction once per extended line amount** (price × qty), never per unit); shipping: `shipping_gross = shipping_net + shipping_tax` through its class rate. | |
| | • **Reconciliation invariants, asserted in tests:** exclusive → `total = subtotal − discount + shipping + tax`; inclusive → `total = subtotal − discount + shipping` **and** `subtotal = Σ net_i + tax_i` **and** `shipping = shipping_net + shipping_tax`. | |
| D3 | **Rounding policy — deliberate and consistent with the per-line model (review Problem C):** exclusive keeps `taxOf` half-up per line; inclusive floors the net per extended line so the remainder lands in the tax. Test vectors assert **both net and tax** on fractional-minor-unit cases (e.g. gross 1001 minor at 10% → net 910, tax 91), plus discounts (allocated against gross before extraction), multiple quantities (single extended-line extraction — no per-unit rounding), multiple classes, shipping extraction verified separately, zero-rated lines, and refunds: **refunds operate on recorded gross amounts; the net/tax split of a refunded amount follows the original line's ratio** (informational, tested). | The reviewer required justification, consistency, and vectors on both outputs across the whole lifecycle. |
| D4 | **Snapshots:** orders gain `prices_contain_tax` (bool, **migration default false** — the historical exclusive model, documented); invoices copy the flag from the order at issuance; historical invoices never recalculate. **Mixed inclusive/exclusive lines cannot occur** (store-wide mode). | Review Problem B/D resolved by construction; policy (d) honored. |
| D5 | **Storefront + preview:** tax preview and PDP price block surface an "incl. VAT"-style marker (i18n ×6) when the store mode is inclusive; the displayed price remains the gross figure the customer pays. Preview receives the **same inputs as checkout** (region, pricing mode, class, discount allocation) — parity preserved. | Display parity; review §6D. |

### Work items
- [ ] Setting: `SettingNameEnum::PRICES_INCLUDE_TAX` (default false) + admin settings UI
      + i18n ×6.
- [ ] `TaxMath` extraction + `TaxGateway` mode path + input/result mode flags;
      `CheckoutAction` resolves the setting, snapshots it, and the **total formula
      switches by mode** (D2).
- [ ] Orders + `order_invoices` additive `prices_contain_tax` columns (default false);
      B.1 kernel schemas updated.
- [ ] Tax preview marker + inclusive reconciliation assertions; storefront i18n ×6;
      Bruno; transfer rules.
- [ ] Pest: the D2 invariant pair (both modes, asserted per order); extraction vectors
      on net AND tax (fractional cases); discounts before extraction; shipping
      extraction separately; multiple quantities/classes; zero-rated; refund gross +
      ratio split; historical default false; preview/checkout parity.

### Edge cases
Mode flip mid-life → historical orders/invoices keep frozen figures (policy d) ·
zero-rate + inclusive → tax 0, flag still snapshots the mode · exclusive mode →
bit-for-bit today's behavior · refund of a fully refunded line → ratio split yields
exactly the recorded net/tax (pinned).

## 7. Slice C.1c — VAT ID collection + offline validation (v1 scope)

> **Scope (unchanged from v2, endorsed by the review at 9/10):** v1 ships VAT
> **collection + offline format validation + evidence snapshotting**. Automatic
> reverse charge defers until a verification policy exists (VIES extension or manual
> admin verification with audit trail); the eligibility seam is documented at
> `TaxGatewayInterface`.

### Design decisions

| # | Decision | Why |
|---|---|---|
| D1 | **`vat_id` (string, nullable) on `user_addresses` + `order_addresses` (snapshot) + `AddressDto`; collected in the storefront address forms, labeled for business customers.** Billing governs the submitted VAT ID; shipping never overrides it; the order snapshots the actually submitted identity; later changes never rewrite historical orders. | The canonical business-identity entity remains a documented future abstraction. |
| D2 | **Offline validation only, v1 semantics (review P1):** `VatIdRule` + a `Tax`-module format validator (per-country regex, EU set first, **including exceptional prefixes — EL for Greece**; prefix must match the billing country); input **normalized before storage** (trimmed, uppercased, spaces/hyphens removed) and the normalized value is what's stored; for countries with no rule, the value is **stored without a validation claim** (option 1 — no status field). Exposed via `TaxGatewayInterface::validateVatId(country, vat): bool`; the docblock states format validity is **not** registration verification. **VAT IDs are excluded from logs and webhook payloads.** The order snapshot is immutable. | The reviewer's option 1; PII/credential-grade handling for the identifier. |
| D3 | **No automatic tax zeroing in v1.** The tax calculation is untouched by this slice; the VAT ID is collected, validated, and frozen as evidence for the future eligibility decision. | Review verdict: approve collection + validation; reject format-based automatic reverse charge. |
| D4 | **Evidence frozen on the order:** additive `buyer_vat_id` (nullable) on orders, persisted by `CheckoutAction` from the billing address; B.1 Phase 2's invoice `buyer_identity` copies it **from the order snapshot at invoice creation**. | Snapshot discipline. |

### Work items
- [ ] `user_addresses`/`order_addresses` migrations + schema constants + `AddressDto` +
      gateway mapping + `AddressRequest` rule.
- [ ] `VatIdRule` + `Tax`-module format validator (country table incl. EL) +
      `TaxGatewayInterface::validateVatId()` + implementation.
- [ ] Orders additive `buyer_vat_id` + `CheckoutAction` persistence; store-address forms
      (conditional VAT input per country, i18n ×6); types; Bruno; transfer rules.
- [ ] Pest: format matrix (valid/invalid/normalized/EL); prefix-vs-billing-country
      mismatch → invalid; snapshot on order; billing-governs rule; historical
      immutability; preview unaffected; VAT ID absent from log/webhook payloads.

### Edge cases
VAT ID on shipping but not billing → billing governs · unknown country → stored
without a validation claim (no status field) · future reverse charge → joins at the
documented seam with its own verification-policy slice.

## 8. Cross-cutting verification & policies

1. **Per slice:** scoped Pest during work; full backend suite + phpstan neutral
   (baseline-splice pattern) + Pint once per slice; storefront/admin builds when those
   apps change; i18n parity ×6 whenever admin surfaces change; live-compose spot check
   at slice end (dev stack + Bruno).
2. **Schema discipline:** additive wherever possible; the one deliberate alteration
   (C.1a's unique-index replacement) ships with dedup detection, rollback semantics and
   dual-engine migration tests.
3. **Policies this phase writes down:**
   (a) *catalogs are events, endpoints are subscriptions* — new events join the webhook
   catalog by adding one map entry;
   (b) *tax configuration failures are loud* — extended by C.1a's region-required rule;
   (c) *unavailable data is omitted, never fabricated*;
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
  extension or manual admin verification with audit trail); the seam is documented.
- **AvaTax / TaxJar** — future `tax-engine` extensions.
- **Full jurisdiction layers** (county/city/districts, Canadian tax components) — the
  C.1a model is deliberately limited (D5); broader engines are extensions.
- **Carrier APIs / labels / live tracking** — the `shipping-carrier` extension type.
- **Multi-warehouse / stock reservations** — unchanged single-stock model.
- **Per-currency price lists** — display conversion stays display-only (B.2 D5 rule).
- **Webhook replay UI, event transformations, per-endpoint payload templates, image
  format conversion (webp/avif outputs)** — extension territory.
- **E-invoicing formats** (Factur-X, Moadian) — extensions (gap roadmap §5).
- **Region admin CRUD** — seed-only in v1 (D6); admin management follows demand.

## 10. Review trail

- v1 (2026-11-07): authored from three parallel code explorations; owner confirmed
  seed scope (US+CA+DE), execution order, machine-route-group token design.
- v2 (2026-11-07): first external review (7.5/10) incorporated — C.1a uniqueness
  corrected (NOT NULL '' scope key), transactional outbox adopted, SSRF guard added,
  C.1b moved to a store-level pricing mode, C.1c rescoped to collection + validation
  (auto-RC removed), plus the full P1 set; policies (d)/(e) added.
- v3 (2026-11-07): second external review (8.5/10 — per-slice verdicts: C.3 8.5,
  C.2 8, C.1a 8.5, C.1b 7.5, C.1c 9) incorporated: **P0-1** C.1b complete accounting
  model (column meanings per mode, reconciliation invariants, per-extended-line
  extraction, refund ratio rule — §6 D2/D3); **P0-2** outbox→delivery recovery split
  (outbox processing vs delivery scheduling; delivery table as durable source of
  truth; claim/lease lifecycle; `source_key` event identity; subscription evaluation
  point fixed) — §4 D2–D4; **P0-3** SSRF upgraded to IP-pinned connections via
  `CURLOPT_RESOLVE` + URL-parsing hardening — §4 D7; **P0-4** C.1a missing-region
  loudness (region-required failure when regional rates exist) + explicit shipping-
  destination jurisdiction — §5 D3/D4; **P0-5** C.3 per-media lock for concurrency +
  deletion coordination — §3 D6. **P1:** versioned variant paths for cache-busting,
  memory-aware pixel limits, failure classification, serialization guarantees;
  signing covers metadata, Retry-After = max(backoff, Retry-After) with cap,
  endpoint-level failure-counter lock, outbox/delivery retention, subscription model,
  bearer-only middleware for machine routes; C.1a limited-model + canonical-region
  documentation + migration dual-engine tests; C.1b extraction-once-per-line +
  refund ratio; C.1c normalization + EL prefix + no-flag option 1 + VAT IDs excluded
  from logs/payloads; version-target clarification (§1); archival contradiction
  resolved (§4 D11); estimates restated as initial estimates.

## 11. Related docs

- `docs/PLATFORM_GAPS_ROADMAP.md` §Phase C — the seed section this plan elaborates;
  §1 placement table and §6 policies bind the slices.
- `docs/plans/gap-b1-invoices.md` — `RefundFinalizedEvent` (Phase 3) and invoice
  `buyer_identity` (Phase 2) are the two C.2/C.1c handoffs.
- `docs/EXTENSION_MARKETPLACE_ROADMAP.md` §5 — additive-migrations + semver-surface
  policies binding all schema changes above.
- `apps/backend/docs/flows/order-state-machine.md` — where tax snapshots sit in the
  lifecycle.
