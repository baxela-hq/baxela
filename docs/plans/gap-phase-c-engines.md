# Phase C — Engine work: tax engine, outbound webhooks + tokens, image variants (v4)

> **Purpose of this document:** Self-contained execution plan for **Phase C** of
> `docs/PLATFORM_GAPS_ROADMAP.md` — the three engine workstreams, split into five
> implementation slices. Like gap-b1/gap-b2, this document stands alone: verified
> state, design decisions, build order, edge cases and tests — readable without
> conversation history.
>
> **Created:** 2026-11-07 · **Owner:** solo maintainer · **Status:** **v4 — approved
> for implementation** (third external review, 9/10: "close to implementation-ready…
> make the focused corrections above, then start C.3"). All review corrections are
> incorporated; the review trail (§10) carries the per-slice verdict history.
> Facts verified 2026-11-07 by three parallel code explorations.
>
> **Execution order (owner-confirmed, unchanged):** **C.3 → C.2 → C.1a → C.1b →
> C.1c**. Slices are strictly sequential: each lands green and committed before the
> next starts. **Size estimates are initial estimates, not release commitments:**
> C.3 ≈ 2 d, C.2 ≈ 4–5 d, C.1a ≈ 2–3 d, C.1b ≈ 2 d, C.1c ≈ 1 d. The target-release
> column in §1 is planning information — it never splits a slice before its
> accounting or reliability requirements are complete (review §8).
>
> **How to use:** Re-verify ⚠-marked facts before relying on them. Conventional
> commits; never commit unless asked. Heavy commands `nice -n 10`. Tests scoped per
> module during work, full suite once per slice. **The engine principle for this
> phase (review §8): a successful happy-path test is not a reliable engine — the
> acceptance tests target concurrent workers, process crashes, retries, configuration
> changes and historical snapshots.**

---

## 1. Where this fits

| Slice | Content | Impl. order | Target release | Why then |
|---|---|---|---|---|
| C.3 | Image variants (queued, idempotent, concurrency-safe) | 1st | v0.2 | Smallest, fully independent of checkout; immediate LCP win |
| C.2 | Outbound webhooks (outbox + claim tokens) + S2S API tokens | 2nd | v0.3 | Unlocks ERP/mobile integrations early; builds on the existing event system only |
| C.1a | Regional tax rates (ISO 3166-2) | 3rd | v0.2–v0.3 | The strategic gap; touches checkout + addresses |
| C.1b | Tax-inclusive pricing (store-level mode, full accounting model) | 4th | v0.3 | Builds directly on C.1a's engine work; **accounting invariants are acceptance criteria** |
| C.1c | VAT ID collection + offline validation | 5th | v0.3 | Completes VAT collection; automatic reverse charge defers to a verification policy (§7) |

**Clarification:** "Target release" is where a feature becomes **available**;
"Impl. order" is when it is **built**. They intentionally differ. Release labels
never influence the implementation sequence.

**Handoffs:** B.1 Phase 3 will add `RefundFinalizedEvent` — it slots into the C.2
webhook catalog as `refund.finalized` the day it lands. B.1 Phase 2's invoice
`buyer_identity` copies the VAT ID **from the frozen order snapshot at invoice
creation**. Phase C's additive-only schema discipline is bound by the marketplace
roadmap §5.2 the moment a third-party extension exists.

**Queue infrastructure is a dependency, not a detail (review §6-A):** before C.3 and
C.2 start, verify that production queue workers run the relevant connections, carry
suitable timeouts, and restart correctly after deployment — and that the scheduled
sweeps (`webhooks:dispatch-deliveries`, `webhooks:dispatch-outbox`) actually run in
production. Documented as a deployment checklist item per slice.

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
| Listener registration | ✅ canonical pattern | Module `EventServiceProvider` `$listen` maps; queued listeners = `ShouldQueue` + `Queueable` |
| Sanctum | ⚠ abilities never used; **session hybrid possible** | Tokens always `['*']`; no ability middleware wired; no token-management surface; `auth:sanctum` can authenticate first-party **session cookies** too — machine routes need explicit bearer-token enforcement |
| Rate limiting | ✅ pattern established | `RateLimiter::for('<name>', fn (Request $r) => Limit::perMinute(config(...))->by(...))` in module providers + `throttle:<name>` routes |
| HTTP client conventions | ✅ thin wrappers + raw-body HMAC precedent | Payment `*Client` classes; inbound verification: Razorpay `hash_hmac` over **raw bytes**, Stripe SDK constructEvent, Telegram constant-time compare; `->timeout()` only in TelegramClient (15s); `Http::withOptions(['curl' => …])` available for cURL-level control |
| Module scaffold | ✅ Audit precedent | `Modules/Audit` layout + `module.json` + `modules_statuses.json`, `Nwidart ModuleServiceProvider` with Event/Route sub-providers |
| Scheduler | ✅ live in prod | Prod compose runs `schedule:work`; per-module `registerCommandSchedules()` precedent |

### C.1 — Tax, addresses, seeds

| Fact | State | Evidence |
|---|---|---|
| Rate scoping | ❌ country-only | `tax_rates`: `country_code char(2)` + unique `(country_code, tax_class_id)`; **no region column anywhere** |
| Resolution | ✅ exact-match, integer-exact | `TaxGateway::calculateForItems`: rates `where(country)->where(is_active)->keyBy(class_id)`; missing/inactive rate = **valid zero**; dangling class = loud failure; coupon allocated largest-remainder BEFORE rates; shipping taxed only via the `is_shipping` class |
| Accounting model | ✅ exclusive-only today | `Total = Subtotal − Discount + Shipping + Tax`; C.1b defines the inclusive counterpart (§6) |
| Checkout wiring | ✅ single call site | `Cart/CheckoutAction` inside the checkout transaction; **tax preview reuses the same calculator** — parity is an acceptance criterion |
| Address chain | ❌ **no region field** | `user_addresses` + `order_addresses` + `AddressDto` lack region; the storefront design's State select "is the backend's required country select" — built but unwired |
| Seed pattern | ✅ + one cautionary tale | `countries.php` flat rows → `insertOrIgnore`; **`time_zones.php` is dead data** — the regions seed must be consumed |
| VAT/company fields | ❌ none | No vat/company identifier anywhere in backend or storefront |
| Tax admin surface | ✅ complete pattern to extend | Routes/controllers/actions/`TaxRateRequest`/`TaxRateResource`/`TaxTransfer::rateRules`/i18n ×6/`schema.ts` |

## 3. Slice C.3 — Image variants

> **DONE 2026-11-08.** Implemented per v4: `intervention/image` v4.4 (GD); presets +
> limits config (`Modules/Media/config/images.php`, pixel budget documented against
> PHP memory × concurrency); `GenerateMediaVariantsAction` with the per-media lock
> (TTL 15 min ≥ 1.5× job timeout 10 min), decode-per-preset (v4's `scaleDown` **mutates
> in place** — a shared instance shrank every subsequent preset to the first width),
> no-upscale clamping, per-variant status, version allocation + atomic publication +
> post-publish prune under the lock, fingerprint-based idempotency; queued listener
> (timeout 600, re-releases on lock contention); `media:regenerate-variants --id/--all`;
> `DeleteMediaAction` under the same lock (original + all recorded variants); exposure
> via `MediaResource`/`MediaGatewayInterface::getVariantsByIds()`; public payload
> attachment (ShowProduct images[], ListProduct/Post `image_variants` — only completed
> entries, original fallback); storefront `lib/image-variants.ts` + the four render
> sites (card srcset from intrinsic widths, gallery large/thumbs, blog card/cover);
> admin media-card thumb + preview dimensions. **10-test ImageVariantsTest** (both
> disks, idempotency, publication no-regression + prune, classification permanent/
> retryable, limits, gif/svg skip, delete under lock, deleted-while-queued, forced
> regeneration). **Verified:** Media 43, Catalog 114, Content 49 all green; production
> storefront + admin builds green; phpstan **[OK]** (baseline spliced for the six
> touched files only — plus a splice-tool fix: baseline messages escape single quotes
> by NEON **doubling** (`''`), not backslashes). Deviations: `image_variants`/`variants`
> ride existing payloads (no new endpoints); MediaResource also exposes `width/height`.

### Design decisions

| # | Decision | Why |
|---|---|---|
| D1 | **`intervention/image` v3, GD driver** — pure PHP, no system binaries. | Shared-hosting path stays viable; GD already present in CI + both Dockerfiles. |
| D2 | **Presets in `Modules/Media/config/images.php`:** `thumb 400w`, `medium 800w`, `large 1600w`, quality 82. jpeg/png/webp sources only; **gif, svg and non-images skipped**. **Source format is preserved end-to-end**: variants keep the source's extension/MIME and are never claimed as another format. | Deterministic presets; no silent format transmutation. |
| D3 | **Input security limits before decode:** read dimensions from the stored file first; reject decode when width or height > 8000 px or total pixels > **`max_pixels` (config, default 20 MP)**. **The limit is stated against aggregate worker memory, not just one image** (GD ≈ 4.6 bytes/px decoded plus intermediate copies): the config documents the relationship between `max_pixels`, PHP `memory_limit`, and the worker's `--max-jobs`-style concurrency, and operators sizing the stack must account for concurrent jobs (review P1-B). Undecodable/corrupt files = **permanent** failure — log + report, original still serves. | A valid image can consume unbounded memory; the budget must respect PHP limits × concurrency. |
| D4 | **Failure classification:** corrupt/undecodable source or unsupported type → **permanent**; storage/S3 outage → **retryable** (nothing marked permanently failed); individual preset encode failure → **partial**. | A temporary storage problem must never become a permanently failed variant. |
| D5 | **Concurrency: per-media atomic lock with an explicit TTL/timeout relationship (review P0-A):** `Cache::lock("media-variants:{id}", ttl: 15 minutes)`, non-blocking acquire, **and the generation job carries a hard timeout of 10 minutes** — the lock TTL is always ≥ 1.5× the job timeout, so a live worker cannot be lock-expired mid-write (the reviewer's simplest option, adopted); a job that cannot acquire the lock re-releases itself (delay 60s); generation re-checks the media row **inside** the lock. `DeleteMediaAction` acquires the same lock, so generate-vs-delete and double-generation cannot interleave. The 15-minute TTL also covers a stalled S3 read bounded by the client timeout. | Sequential idempotency alone does not survive concurrent workers; TTL > timeout closes the expiry race. |
| D6 | **Idempotent generation with an atomic publication protocol (review P0-B):** generation versions are **allocated inside the lock** (`version = metadata.version + 1` — two concurrent regenerations cannot both claim v4). Sibling paths: `{dirname}/{filename}-{preset}-v{N}.{extension}`. **Publication is the commit point:** (1) allocate version under lock → (2) write all new variant files → (3) build candidate metadata → (4) **atomically publish** the metadata → (5) prune previous-version files. **A failed regeneration never replaces the currently published set:** if the attempt ends with zero completed variants, nothing is published (old version stays live, failure recorded); if some presets complete, the published metadata **merges per preset — completed presets take the new entry, failed presets keep their previous published entry, and absent-before presets appear only when completed** (no variant ever regresses; partial publication is explicit, satisfying both review rounds). Old-version files are pruned only after successful publication. | Deterministic paths + cache-safe regeneration + no-regression publication, with version allocation inside the critical section. |
| D7 | **Per-variant status in `metadata`:** `{"version": N, "width": W, "height": H, "variants": {"thumb": {"status": "completed", "path": …, "width": …}, "large": {"status": "failed", "reason": …}}}` — partial failures visible; consumers read only `completed` entries. | Partial generation remains understandable; repair is explicit. |
| D8 | **Regeneration path:** artisan `media:regenerate-variants {--id=} {--all}` — allocates the next version under the lock and runs the D6 protocol. Documented for preset changes; not an admin button in v1. | Preset changes must not require re-uploading originals. |
| D9 | **Exposure:** `MediaResource` gains `width/height/variants`; `MediaGatewayInterface::getVariantsByIds()` serves Catalog's loose `media_id` refs; public payloads attach `variants` where the media row resolves — **only `completed` variants advertised; failed/missing fall back to the original URL** (serialization-tested). | Correctness over coverage. |
| D10 | **`DeleteMediaAction`** acquires the D5 lock, enumerates all variant paths across **all versions still on disk** plus the original, deletes them, and marks the metadata so a queued generation re-check finds the row gone. | Orphan-proof lifecycle. |
| D11 | **Storefront:** `ApiImage`/`ApiProduct`/`ApiPost` gain `variants?: {thumb?, medium?, large?}` each `{url, width}`; product card `srcset` from **actual intrinsic widths** with `sizes`; gallery main = largest completed, thumbnails = thumb; blog card/cover likewise; JSON-LD keeps the largest/original. Admin `media-card` thumb; `preview-sheet` dimensions + per-variant status. | srcset descriptors match reality. |

### Work items
- [x] composer: `intervention/image`; config presets + limits + documented TTL/timeout
      relationship (D1–D3, D5).
- [x] `GenerateMediaVariantsAction` (limits → classification → version allocation
      under lock → presets → **atomic publication protocol** → prune) + queued listener
      with hard job timeout (D4–D7).
- [x] `media:regenerate-variants` (D8); `DeleteMediaAction` under lock (D10);
      `MediaResource` exposure (D9).
- [x] `MediaGatewayInterface::getVariantsByIds()` + Catalog/Content payload attachment.
- [x] Storefront: types + four render sites with honest srcset (D11); admin card/preview.
- [x] Pest: both disks; idempotency + **concurrency** (lock excludes a second worker;
      version allocation under lock — two concurrent regenerations, one version); the
      publication protocol (failed attempt → old set stays live; merge semantics; prune
      after publish); per-variant partial-failure state; permanent vs retryable;
      oversized/corrupt rejection; gif/svg skip; delete under lock; deleted-while-queued
      no-op; regeneration; serialization guarantees. Admin i18n ×6.

### Edge cases
Legacy rows → original URL only · corrupt image → permanent `failed`, original serves ·
S3 outage → retryable · media deleted mid-queue → no-op under lock · preset larger than
the original → skipped, intrinsic width advertised · lock lost to a stalled operation →
TTL(15 min) > timeout(10 min) keeps the worker owner · aggregate memory across
concurrent jobs → documented sizing rule.

## 4. Slice C.2 — Outbound webhooks (transactional outbox + claim-token delivery) + S2S API tokens

> **DONE 2026-11-08.** Implemented per v4: new `Modules/Webhooks` module (Audit
> scaffold); transactional outbox (`webhook_events` with per-transition `source_key`
> dedupe; sync listeners join the firing transaction — rollback-atomicity test-proven);
> claim-token delivery lifecycle (`pending → processing → terminal`, stale-worker
> writes rejected by conditional update — test-proven); scheduled dispatch sweep
> (every minute) as the recovery path; HMAC-SHA256 signing over
> `timestamp.event_id.delivery.body` (worked test vector; secrets encrypted at rest,
> shown once, rotation endpoint); SSRF guard with per-attempt DNS re-validation and
> `CURLOPT_RESOLVE` IP pinning (proxies off, redirects = permanent failure, private
> blocks test-proven); response classification with `Retry-After = max(backoff, ra)`
> capped 24h; endpoint auto-disable at 10 consecutive failures under a lock (9-1-1
> test-proven); archive-not-delete + 90-day retention; delivery log with attempt
> history + `webhooks:retry-delivery`; admin endpoints CRUD under route-name
> permissions; i18n ×6. S2S tokens: Auth minting (explicit abilities, wildcard
> rejected at mint AND in middleware, audit-logged, plaintext once) + read-only
> `api/v1/machine/*` orders/products routes (bearer-only middleware rejects sessions,
> per-token rate limit, ownership-scoped queries). **20 Pest tests; full backend
> suite 1,122 passed; phpstan OK.** Handoff note: `refund.finalized` joins the
> catalog when B.1 Phase 3 lands `RefundFinalizedEvent`.

### Design decisions

| # | Decision | Why |
|---|---|---|
| D1 | **New `Modules/Webhooks` module** (Audit scaffold + `Jobs/`). | Smallest coherent home. |
| D2 | **Transactional outbox with per-transition source identity (review P0-D):** `webhook_events` (`event_id` uuid PK, **`source_key` string unique**, `event_type`, `payload` JSON — public, `created_at`, `processed_at` nullable). **`source_key` rules are defined per event type from the business transition, not the aggregate:** `order.created:{order_id}` · `order.paid:{payment_id}` (each successful payment confirmation transition emits once — a reversed-then-collected payment is a new transition with a new settled id) · `order.cancelled:{order_id}:{cancelled_at_unix}` · `order.shipped:{order_id}:{shipment_id}` · `shipment.shipped:{shipment_id}` · `refund.processed:{refund_uuid}` · `return.requested:{return_id}`. Synchronous catalog listeners insert-or-ignore in the firing transaction (rollback-safe). | A legitimate later transition must never be suppressed by an aggregate-level key; each business transition emits exactly once. |
| D3 | **Two separated responsibilities:** **(A) Outbox processing** — `ProcessOutboxEventJob(event_id)`: resolves subscribed endpoints (evaluation happens **here** — deterministic), creates delivery rows, marks `processed_at` — one transaction, `(endpoint_id, event_id)` unique guards double-processing. **(B) Delivery scheduling** — the delivery table is the durable source of truth: `webhooks:dispatch-deliveries` (every 5 min) finds claimable deliveries and enqueues jobs. Fast path: processing also enqueues (a lost enqueue is harmless). **The sweep itself claims atomically** (review §2-C): it selects claimable deliveries `WHERE status IN ('pending','failed_retryable') AND (claim_token IS NULL OR claimed_at < now() - lease)` with an atomic `UPDATE … SET claim_token = ?` so two scheduler instances cannot repeatedly enqueue the same deliveries — duplicates become an efficiency footnote, not a correctness issue. | Crash between "processed" and "enqueued" loses nothing; duplicate enqueues are reclaimed idempotently. |
| D4 | **Claim-token delivery lifecycle (review P0-A):** `webhook_deliveries.status ∈ pending → processing → succeeded | failed_retryable | failed_permanent`; claiming generates a **unique `claim_token`** and sets `claimed_at`; **every subsequent state update is conditional on the claim token still being current** (`UPDATE … WHERE id=? AND claim_token=?`) — the classic stale-worker problem is closed: worker A stalls, its lease expires, worker B claims with token B, A's late completion **fails its conditional update** and is discarded. Lease TTL 10 minutes, reclaimed by the sweep; every attempt appends to an **attempt history** (see D9). | The lease alone does not prevent stale-worker overwrites; the ownership token does. |
| D5 | **Stable identifiers, two levels:** `event_id` (shared by all deliveries of an event) and `delivery_id` (per endpoint, stable across retries); headers `X-Baxela-Event-Id` + `X-Baxela-Delivery`. | Correlation across endpoints. |
| D6 | **Public payload schema — explicit serializers, never `event->toArray()`:** per-event-type builders producing `{"api_version": "v1", "type": …, "occurred_at": …, "data": {…}}`; ids, codes, amounts, statuses only — **no customer PII, no VAT IDs**; **serializers never dump a whole AddressDto** (explicit field lists). Internal events can change without breaking integrations. | Integration contract control. |
| D7 | **SSRF guard — IP-pinned delivery with the reviewer's hardening details (review P1-E):** `UrlGuard` validates at create/update **and resolves at every attempt**: HTTPS-only (production); reject userinfo/credentials, non-allow-list ports (default 443), malformed hosts, non-canonical IP-literal encodings; resolve **the full CNAME chain to the final A/AAAA records and validate every resolved address** (public only); **IPv6 zone identifiers stripped and mapped-IPv6 (`::ffff:0:0/96`) normalized** before checks; **pin via `CURLOPT_RESOLVE`** (`host:port:validated-ip`, rotated across attempts) with TLS verification intact; **the delivery client runs without any configured proxy** (an environment proxy could bypass pinning — proxy support is explicitly disabled for this client); **no connection reuse across deliveries** (a fresh client per attempt — a pooled connection could silently reuse a pre-validation address); **DNS re-resolved and re-validated on every retry attempt** (answers may change between attempts). Redirects disabled (3xx permanent). Connection/total timeouts + response-size cap. A curl-level integration test (not `Http::fake`) proves the pin — `Http::fake` cannot demonstrate that cURL connects to the pinned IP while preserving TLS verification. An outbound network policy is documented as defense in depth. | The reviewer's five-point hardening list, adopted in full; the feature may be described as SSRF-pinned. |
| D8 | **Canonical signing — metadata authenticated:** `signed_payload = "{timestamp}.{event_id}.{delivery_id}.{raw_body}"`, `X-Baxela-Signature: sha256=<hex>`; `X-Baxela-Timestamp`/`X-Baxela-Event-Id`/`X-Baxela-Delivery` carry the values; receivers verify constant-time + timestamp tolerance (default 5 min) — **the documented receiver-side algorithm ships with a worked test vector**. Since IDs are inside the signature, headers are authenticated rather than merely informational. Secrets: **encrypted at rest**, **displayed once**, rotation = regenerate (old invalid immediately, documented). | Replay + metadata tampering covered. |
| D9 | **Attempts = real HTTP attempts (review P1-B):** a persisted **attempt-history record is created before the request is sent and finalized after the response or transport failure** — one record per actual HTTP attempt (timestamps, status or sanitized error category, duration); Laravel job attempts and delivery attempts are kept consistent because the job is the only writer and the history row is the unit of bookkeeping. **At-least-once is documented:** if the process dies after the request reaches the receiver but before persistence, the next claim resends — the stable `delivery_id` and receiver-side deduplication are therefore essential and stated in the integration docs. `$tries = 5` (first attempt immediate + four backoff steps `[30, 120, 600, 3600]`). | Counting a dispatch as an HTTP attempt would corrupt the ledger; the review's caveat is explicit. |
| D10 | **Response classification:** 2xx success · 3xx permanent (redirects disabled) · 400/401/403 permanent · 408/429/5xx/timeout retryable. **Delay = max(backoff step, Retry-After), capped at 24 h; invalid/negative Retry-After ignored** (v2's contradiction resolved). | Review table, verbatim. |
| D11 | **Auto-disable with defined ordering (review P1-F):** terminal-state transitions apply `consecutive_failures` **inside the endpoint lock**; **"consecutive" means the order in which attempts reach terminal state** (serialized by the lock), documented as *not necessarily event chronology* — a sequence-number semantics is explicitly deferred. Success resets to zero; disable at 10. | The reviewer's v1 definition, adopted. |
| D12 | **Archival + retention (review P1-D):** delete = **archive** (in-flight delivery completes; **pending unclaimed delivery is cancelled** (`failed_permanent`, reason `endpoint_archived`); no new deliveries); **outbox retention:** an outbox event is deletable only when **all its deliveries are terminal** and the event is older than 90 days — the scheduled prune removes deliveries then their events (FK ordered); **failed deliveries receive the same 90-day retention**; archival is not deletion. | Deterministic lifecycle; history retained with a stated policy. |
| D13 | **Admin + CLI surface:** endpoints CRUD (secret shown once) + `regenerate-secret` + `test` event + deliveries list/show with **diagnostics fields (attempt timestamps, HTTP status, sanitized error category, duration, scheduled retry)** — never secrets or full sensitive response bodies; **`webhooks:retry-delivery {delivery_id}`** (review §6-B): retries only eligible terminal failures, preserves event/delivery IDs, records the initiating admin; route-name permissions; i18n ×6; Bruno. | Operators can recover from exhausted retries without database surgery. |
| D14 | **S2S tokens — ownership + full enforcement:** minted **only** to a designated machine/service user (audit-logged creation/revocation, plaintext once, explicit default-deny abilities `orders.read`/`products.read`, expiration, immediate revocation). `api/v1/machine/...` is read-only, per-token rate-limited, and guarded by middleware that **requires a bearer personal-access token, rejects session-authenticated requests, rejects wildcard-ability (`['*']`) tokens, verifies the token is unexpired/unrevoked and its machine user is active**, and applies **an explicit ability check on every route**. **The ownership boundary is enforced in the query/action layer** — machine orders reads are scoped to the owning account's data, not just at authentication. PII: machine tokens read the merchant's **own** store data — documented as in-scope for an ERP integration. | Review P1-F's final safeguard list, adopted item by item. |

### Work items
- [x] Scaffold `Modules/Webhooks`; migrations (`webhook_events` with `source_key`,
      `webhook_endpoints` encrypted secret + archive, `webhook_deliveries` with claim
      token/lease/attempt history); models + schemas.
- [x] Public payload serializers (D6); sync outbox listeners with per-transition
      `source_key` (D2); `ProcessOutboxEventJob` (D3); `webhooks:dispatch-deliveries`
      sweep with atomic claim (D3).
- [x] `UrlGuard` (CNAME chain, zone-id/mapped-IPv6 normalization, port/userinfo rules)
      + `CURLOPT_RESOLVE` pinning + fresh-client-per-attempt (D7); `DeliverWebhookJob`
      with claim-token lifecycle (D4) + canonical signing + test vector (D8) +
      classification (D10) + attempt history (D9) + endpoint-lock auto-disable (D11).
- [x] Archival + retention commands (D12); admin CRUD + `webhooks:retry-delivery` (D13);
      `auth:sync-permissions`; i18n ×6; Bruno.
- [x] S2S tokens: Auth admin minting (D14); `machine` group + bearer-only/ability
      middleware + ownership-scoped queries + rate limiter; scope docs.
- [x] Pest: outbox atomicity + per-transition `source_key` (the reviewer's four cases:
      multiple partial payments, reversed-then-collected, multiple shipments, multiple
      refunds); serializer shape + PII/VAT absence + no AddressDto dumps; UrlGuard
      matrix (CNAME-to-private, mapped-IPv6, credentials, odd ports, non-HTTPS) +
      **curl-level pinning integration test** (TLS verification intact); signing vector;
      **claim-token stale-worker test** (A's late write rejected); claim exclusivity
      (two workers, one HTTP call); lease-expiry reclaim; sweep atomic claim; response
      classification; Retry-After max/invalid; attempt history vs job attempts;
      endpoint-lock auto-disable (9-1-1); archival lifecycle; retention; ability
      enforcement + **session + wildcard-token rejection**; ownership-scoped queries;
      rate limit; token lifecycle audit; retry CLI eligibility.

### Edge cases
Transaction rollback → outbox row rolls back (no phantom webhook) · duplicate listener
invocation → `source_key` dedupe · lost enqueue → sweep claims · receiver 429 with
huge Retry-After → 24 h cap · invalid Retry-After → backoff step · process dies
post-send pre-persist → resend (at-least-once), receiver dedupes on `X-Baxela-Delivery`
· endpoint archived mid-flight → in-flight completes, pending cancelled, none new ·
DNS answers change between retries → re-resolved and re-pinned per attempt · endpoint
deleted by mistake → archive restores.

## 5. Slice C.1a — Regional tax rates

> **DONE 2026-11-08.** Implemented per v4: `region_code` NOT NULL DEFAULT '' scope key
> on `tax_rates` (unique `(country, region, class)` swapped dedup-safe, rollback warning
> in the migration; duplicate-''-insert rejection test-proven); `core_regions` table +
> US 51/CA 13/DE 16 seed consumed by the seeder (live consumer: `RegionCodeRule` +
> `GET /core/public/regions?country_code=`, cached); `TaxGateway` precedence
> (region-specific → country-wide, inactive = removed from consideration) + the
> **region_required loud failure** (regional rates configured + no region on the input
> → `TaxConfigurationException`, closing the California hole); region through the
> address chain (`user_addresses`/`order_addresses` nullable columns — stored rows
> never re-validated, `AddressDto`, `AddressRequest` pair-validated via
> `RegionCodeRule`), checkout + tax preview pass the destination region; tax admin
> surface (request with the **implicit `ScopedRateUniqueRule`** — Laravel skips
> ordinary rules for '' values, so scope-key uniqueness needed an implicit rule;
> actions normalize to ''; resource exposes null for ''), `TaxTransfer` region joins
> the natural key; storefront region selects (address-manager + checkout inline form,
> rendered only when the country has seeded regions) + i18n ×6; admin tax-rates
> region field + column badge + `use-regions` hook + i18n ×6. **10-test
> RegionalTaxTest** (precedence matrix, loud failure, inactive fallback, DB-level
> duplicate rejection, resource null-vs-code, pair validation, endpoint, seed counts).
> **Verified:** Tax 47, full backend **1,132 passed**, both production builds green,
> phpstan **[OK]**, i18n parity green. Note: the recurring container-owned daily log
> file blocked the first full-suite run (removed; owner may want a uid/umask fix).

### Design decisions

| # | Decision | Why |
|---|---|---|
| D1 | **Uniqueness via a NOT NULL scope key:** `region_code` **`string(10) NOT NULL DEFAULT ''`** ('' = country-wide) with unique `(country_code, region_code, tax_class_id)` — duplicate country-wide inserts rejected on both engines, proven by a Pest test. | v2 correction, reviewer-endorsed. |
| D2 | **Resolution precedence + inactive semantics:** region-specific (present + active) → country-wide (present + active) → valid zero. **Deactivating a regional rate = it stops being considered; the country rate applies again** (documented in the gateway docblock + `docs/tax.md`). | One meaning for `is_active`. |
| D3 | **Missing-region loudness:** (1) no rates for the country → valid zero (intentional); (2) **regional rates exist + address lacks a region → loud `tax.configuration.region_required` failure** aborting checkout (storefront surfaces a region-required state); (3) inactive regional → country fallback; (4) unknown region → address validation rejects. | The California under-collection hole, closed. |
| D4 | **Jurisdiction = the shipping destination** (billing only when no shipping address exists); `CheckoutAction` and tax preview resolve identically — parity preserved. | Explicit, not interpretive. |
| D5 | **Seed drift never breaks stored data (review P1-A):** rejection is limited to **submission-time validation** of new/edited addresses; **already-stored customer addresses and historical snapshots are not re-validated against the seed** — checkout with a stored address whose region is no longer seeded keeps the stored region code, and tax resolution falls back per D2 (region lookup misses → country rate). The public regions endpoint returns **all seeded regions for the country** (deterministic: all, for the initial US/CA/DE scope). | Stored customer data must survive seed updates. |
| D6 | **Migration rollback warning (review P1-B):** the unique-index replacement's rollback **drops regional rates** — the migration docblock and `docs/tax.md` carry a deployment warning: *do not roll back after regional rates exist in production unless they were exported or the operator accepts the loss.* Tested against seeded data on MySQL and SQLite (dedup pre-check included). | The reviewer's operational note, made unmissable. |
| D7 | **Limited model + honest docs:** one regional rate per region × class; **no county/city/district layers, no Canadian tax-component splits** — the public documentation states this is a limited regional-tax model, not a complete jurisdiction engine (review P1, final note). | Public honesty matches the non-goals. |
| D8 | **Canonical representation `US-CA` / `CA-ON` / `DE-BY` everywhere** (validation, admin, API, resolution) via one normalizer; **`core_regions` naming throughout**; seed updated via PRs; no region admin CRUD in v1. Address chain: `user_addresses`/`order_addresses` migrations, `AddressDto`, `AddressRequest` + `RegionCodeRule`, storefront region select + types. Tax admin + `TaxTransfer` extended. | One representation, one name. |

### Work items
- [x] Migration (dedup-safe unique replacement, dual-engine tests, rollback warning) +
      schema/model constants.
- [x] Core: `core_regions` + `regions.php` seed + seeder + model + public endpoint
      (all seeded regions per country) + `RegionCodeRule` + normalizer.
- [x] `TaxGateway` precedence/inactive/region-required rules (D2–D3) +
      `TaxCalculationInput.region_code`; `CheckoutAction` shipping-destination
      resolution (D4).
- [x] Address chain migrations + DTO + `AddressRequest` + storefront region select
      (×6 locales).
- [x] Tax admin + `TaxTransfer` + Bruno; i18n ×6.
- [x] Pest: the §5 D2–D4 matrix (incl. loud region-required failure; jurisdiction =
      shipping destination; **stored-address seed-drift behavior**); duplicate-insert
      rejection; migration against seeded data (both engines ⚠); checkout snapshot;
      transfer round-trip; admin CRUD.

### Edge cases
No seeded regions for a country → no select, '' region, unchanged behavior · seed drift
→ stored addresses keep working; new submissions validated · regional rate inactive →
country fallback · multiple regions per class coexist with the single country-wide rate.

## 6. Slice C.1b — Tax-inclusive pricing (store-level mode, complete accounting)

> **DONE 2026-11-08.** Implemented per v4: `prices_include_tax` Setting (enum migration +
> '0'/'1' validation + cached `ResolvesPricingMode` bound to a Core contract, flushed on
> settings write); `TaxMath::extractInclusive` (net ⌊gross×10⁶÷(10⁶+units)⌋, tax = remainder)
> + `splitInclusiveRefund` (frozen-ratio partial, exact full); gateway inclusive path
> (extraction on the after-discount GROSS per line + shipping through its class rate;
> exclusive bit-for-bit unchanged); `TaxCalculationInput.prices_include_tax` /
> `TaxResult.prices_contain_tax` / preview `prices_contain_tax`; orders + order_invoices
> additive `prices_contain_tax` (default false = historical); OrderGateway switches the
> totals formula — inclusive NEVER adds the extracted tax (double-charge closed);
> IssueInvoiceAction copies the flag; admin settings toggle + i18n ×6; checkout tax-row
> marker + PDP "incl. VAT" marker (public settings list) + i18n ×6. **7 InclusivePricingTest
> + the order-formula test** (both modes asserted; 305.00 vs 352.40 on identical input).
> Affected suites 238 green; full backend **1,152 passed**; builds + parity + phpstan OK.

### Design decisions

| # | Decision | Why |
|---|---|---|
| D1 | **Store-level mode: `prices_include_tax` Setting** (admin UI, i18n ×6). The per-rate flag stays dropped; **mixed inclusive/exclusive lines cannot occur**. | Owner + reviews; one mode → unambiguous snapshots. |
| D2 | **Accounting model with discount reconciliation (review P0-1 + P1-A) — definitions first:** **`subtotal` = Σ gross line amounts *before* discount** (gross = displayed price × qty); `discount` = the coupon allocated across gross line amounts (existing largest-remainder logic, operating on the correct gross base); **`line_gross_after_discount` = gross − allocated discount**; per line in inclusive mode the extraction then applies to the **after-discount** amount: `net_after = ⌊gross_after × 10⁶ ÷ (10⁶ + rateUnits)⌋`, **`tax_after = gross_after − net_after`** (remainder to the tax). **Line invariant: `gross_after_discount = net_after_discount + tax_after_discount`** — asserted per line, closing the v3 gap the reviewer identified. | The v2 invariant described undiscounted lines only; discounts are now inside the reconciliation. |
| D3 | **Column meanings per mode (complete):** `subtotal` (gross before discount), `discount` (allocated), `shipping` (gross), `tax_amount` (extracted, informational in inclusive mode), and the totals: **exclusive `total = subtotal − discount + shipping + tax`; inclusive `total = subtotal − discount + shipping`** — the customer never pays more than the displayed gross figures imply. | The reviewer's table, made normative. |
| D4 | **Extraction + rounding (justified):** extraction **once per extended after-discount line amount** (never per unit); net = ⌊…⌋ floor, tax = remainder (exclusive keeps `taxOf` half-up — the asymmetry is deliberate and vector-tested on **both** outputs, e.g. gross 1001 minor at 10% → net 910, tax 91). Shipping: gross in both modes; **shipping discounts do not exist in v1 — stated explicitly**; if ever added, they must be allocated before shipping-tax extraction (documented seam). | Review P1-B's checklist. |
| D5 | **Snapshots:** orders gain `prices_contain_tax` (default **false**, the historical model — documented); **invoice line items carry the order's frozen figures** (gross, discount, tax — already copied by B.1's snapshot) and the **net-after-discount is derived exactly** (`gross_after − tax`), never inferred from the mode flag; **invoice lines never recalculate**. Mixed lines impossible (store-wide mode). | Review P1-D: invoices must not infer from the flag. |
| D6 | **Refund split rule (review P1-C):** refund gross is authoritative (ledger unchanged); for a partial refund of a line, **refund net = ⌊refund_gross × (frozen net_after ÷ frozen gross_after)⌋ and refund tax = refund_gross − refund_net**; **a full refund returns the exact original frozen net/tax amounts** (no accumulated rounding drift); the split is never recomputed from current rates. | Fractional minor units get an exact rule. |
| D7 | **Storefront + preview:** "incl. VAT"-style marker (i18n ×6) when the store mode is inclusive; preview receives the same inputs as checkout (jurisdiction, mode, classes, discount allocation) — the calculator remains the single source of truth for preview, checkout, invoices and storefront display. | Review §6-C. |

### Work items
- [x] Setting enum + admin settings UI + i18n ×6.
- [x] `TaxMath` extraction + `TaxGateway` mode path + input/result flags;
      `CheckoutAction` resolves the setting, snapshots it, switches the total formula
      by mode (D3).
- [x] Orders + `order_invoices` additive `prices_contain_tax`; B.1 kernel schemas
      updated; invoice line derivation documented (D5).
- [x] Tax preview marker + reconciliation assertions; storefront i18n ×6; Bruno;
      transfer rules.
- [x] Pest: per-line invariant (`gross_after = net_after + tax_after`) with discounts;
      order-level invariants in both modes; extraction vectors on net AND tax
      (fractional cases); shipping extraction; zero-rated; **partial-refund rounding
      rule + full-refund exactness**; historical default false; preview/checkout
      parity; mode-flip immutability.

### Edge cases
Mode flip mid-life → frozen figures stand (policy d) · zero-rate + inclusive → tax 0,
flag snapshots the mode · exclusive → bit-for-bit today · 100% rate inclusive →
net = gross ÷ 2 (pinned) · refund of a fully refunded line → exact original split.

## 7. Slice C.1c — VAT ID collection + offline validation (v1 scope)

> **DONE 2026-11-08.** Implemented per v4: `vat_id` on `user_addresses` +
> `order_addresses` (frozen) + `buyer_vat_id` on orders; `VatIdValidator` (27 per-country
> EU patterns + the EL-for-Greece exception via prefix mapping; rule-less countries store
> without a validation claim — option 1, no status field); `TaxGatewayInterface::
> validateVatId()` (docblock: format ≠ registration); `VatIdRule` in the User module (no
> value echo in failures); AddressRequest normalization (uppercase, separators stripped)
> before validation and storage; OrderGateway freezes region + vat on the order address and
> buyer_vat_id on the order (fillable gap found by the test — region from C.1a had been
> silently dropped too, now fixed); IssueInvoiceAction copies vat_id from the frozen order
> into `buyer_identity`; storefront VAT inputs (address manager + checkout inline form,
> optional) + `ApiAddress.vat_id` + i18n ×6. **11-test VatIdTest** (format matrix incl.
> EL + normalization + no-echo, normalized storage, rule-less storage) + order-freeze and
> invoice-identity tests. No automatic tax zeroing anywhere — the reverse-charge seam
> stays documented.

> **Scope (unchanged since v2; 9/10 from the reviewer):** VAT **collection + offline
> format validation + evidence snapshotting**. Automatic reverse charge defers to a
> verification policy; the seam is documented at `TaxGatewayInterface`.

### Design decisions

| # | Decision | Why |
|---|---|---|
| D1 | **`vat_id` on `user_addresses` + `order_addresses` (snapshot) + `AddressDto`**; storefront forms label it for business customers; billing governs (shipping never overrides); order snapshots the submitted identity; later changes never rewrite history. | Established in v2, endorsed. |
| D2 | **Offline validation, isolated per country (review §5):** `VatIdRule` + a `Tax`-module validator with **country-specific normalizer/validator classes behind one interface** (so rules are replaceable/extendable); EU set first, **EL for Greece**, prefix must match the billing country; input **normalized before storage** (trimmed, uppercased, separators removed); countries without a rule → stored without a validation claim (option 1, no status field); docblock: format validity ≠ registration verification. **Privacy: VAT IDs never appear in exception messages, validation errors never echo the submitted value, API resources expose the identifier only where required, and logs/audit/webhook serializers never serialize a whole AddressDto.** | Review §5's checklist, adopted. |
| D3 | **No automatic tax zeroing in v1;** the calculation is untouched; the VAT ID is collected, validated, and frozen as evidence. | Reviewer-endorsed scope. |
| D4 | **Evidence frozen on the order:** additive `buyer_vat_id` on orders, persisted from billing; B.1 Phase 2's invoice `buyer_identity` copies it from the order snapshot at creation. | Snapshot discipline. |

### Work items
- [x] Migrations + schema constants + DTO + gateway mapping + `AddressRequest` rule.
- [x] `VatIdRule` + per-country validator classes (isolated, replaceable) +
      `TaxGatewayInterface::validateVatId()` + implementation.
- [x] Orders `buyer_vat_id` + persistence; store-address forms (conditional input,
      i18n ×6); types; Bruno; transfer rules.
- [x] Pest: format matrix (valid/invalid/normalized/EL); prefix-vs-billing mismatch;
      snapshot; billing-governs; historical immutability; **error-message and log
      hygiene (no VAT echo)**; preview unaffected.

### Edge cases
Shipping-side VAT ignored (billing governs) · unknown country → stored without a
validation claim · future reverse charge → joins at the documented seam.

## 8. Cross-cutting verification & policies

1. **Per slice:** scoped Pest during work; full backend suite + phpstan neutral
   (baseline-splice pattern) + Pint once per slice; storefront/admin builds when those
   apps change; i18n parity ×6 when admin surfaces change; live-compose spot check at
   slice end.
2. **Schema discipline:** additive wherever possible; C.1a's unique-index replacement
   ships with dedup detection, dual-engine migration tests and the rollback warning.
3. **Policies:**
   (a) *catalogs are events, endpoints are subscriptions*;
   (b) *tax configuration failures are loud*;
   (c) *unavailable data is omitted, never fabricated*;
   (d) *money calculations are reproducible* — historical orders, invoices and refunds
   never depend on current rates, seeds, or settings;
   (e) *external inputs are untrusted* — validated at their boundaries;
   (f) *the tax calculator is the single source of truth* — preview, checkout,
   invoices and storefront display all consume the same calculation path with the same
   jurisdiction, pricing mode, discount allocation and rounding rules.
4. **Queue/scheduler readiness (review §6-A):** each of C.3/C.2 ends with a deployment
   checklist item verifying prod workers, timeouts, post-deploy restarts, and that the
   scheduled sweeps run in production.
5. **Suggested commits:** one per phase within each slice — e.g.
   `feat(backend/media): queued idempotent image variant generation`,
   `feat(storefront): responsive image variants`,
   `feat(backend/webhooks): transactional outbox and signed webhook delivery`,
   `feat(backend/auth): scoped s2s tokens and machine api`,
   `feat(backend/tax): region-scoped tax rates`,
   `feat(backend/tax): store-level tax-inclusive pricing`,
   `feat(backend/tax): vat id collection and offline validation`.

## 9. Explicit non-goals (restated; silence reads as oversight)

- **Automatic reverse charge** — deferred until a verification policy exists; seam
  documented.
- **AvaTax / TaxJar** — future `tax-engine` extensions.
- **Full jurisdiction layers** (county/city/districts, Canadian tax components) —
  documented limitation of the C.1a model (D7); public docs equally honest.
- **Carrier APIs / labels / live tracking** — the `shipping-carrier` extension type.
- **Multi-warehouse / stock reservations** — unchanged single-stock model.
- **Per-currency price lists** — display conversion stays display-only.
- **Webhook replay UI, event transformations, per-endpoint payload templates, image
  format conversion (webp/avif outputs)** — extension territory.
- **E-invoicing formats** (Factur-X, Moadian) — extensions.
- **Region admin CRUD** — seed-only in v1.

## 10. Review trail

- v1 (2026-11-07): authored from three parallel code explorations; owner confirmed
  seed scope (US+CA+DE), execution order, machine-route-group tokens.
- v2 (2026-11-07): first external review (7.5/10) — C.1a uniqueness corrected,
  transactional outbox adopted, SSRF guard added, C.1b store-level mode, C.1c
  rescoped, P1 set, policies (d)/(e).
- v3 (2026-11-07): second external review (8.5/10) — C.1b accounting model,
  outbox→delivery split + claim lifecycle, IP-pinned SSRF, region-required loudness +
  shipping-destination jurisdiction, per-media locks, plus the full P1 set and
  policies (f)-precursor.
- v4 (2026-11-07): third external review (9/10 — "make the focused corrections above,
  then start C.3") incorporated: **P0** C.3 lock-TTL/timeout relationship + version
  allocation under lock + no-regression publication protocol (§3 D5/D6); C.2
  claim-token ownership rejecting stale-worker writes (§4 D4); per-transition
  `source_key` rules (§4 D2). **P1** real HTTP attempt history + at-least-once
  documentation (D9); CNAME/zone-id/proxy/connection-reuse pinning hardening +
  curl-level test (D7); consecutive-failure ordering semantics + ownership-scoped
  machine queries + wildcard-token rejection (D11/D14); destructive-rollback warning +
  seed-drift stored-address behavior + deterministic regions endpoint (§5 D5/D6);
  discount-reconciliation invariant + subtotal definition + shipping-discount statement
  + partial-refund rounding + invoice-line derivation (§6 D2/D4/D6); VAT rule
  isolation + privacy hygiene (§7 D2); retry CLI + diagnostics + queue-readiness
  checklist (D13, §8-4); calculator-as-single-source accepted criterion (§6 D7,
  §8-3f).

## 11. Related docs

- `docs/PLATFORM_GAPS_ROADMAP.md` §Phase C — the seed section this plan elaborates.
- `docs/plans/gap-b1-invoices.md` — `RefundFinalizedEvent` (Phase 3) and invoice
  `buyer_identity` (Phase 2) are the two C.2/C.1c handoffs.
- `docs/EXTENSION_MARKETPLACE_ROADMAP.md` §5 — additive-migrations + semver-surface
  policies binding all schema changes above.
- `apps/backend/docs/flows/order-state-machine.md` — where tax snapshots sit in the
  lifecycle.
