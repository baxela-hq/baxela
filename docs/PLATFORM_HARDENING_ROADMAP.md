# Platform Hardening Roadmap — untracked gaps the other plans don't cover

> **Purpose of this document:** Register the platform gaps found in the
> 2026-11-10 whole-repo review that fall outside every existing roadmap
> (launch, gaps A–D, extension marketplace). None of these items is tracked
> anywhere else; silence reads as oversight. Phases are lettered **H–L** to
> avoid collision with all existing lettering.
>
> **Created:** 2026-11-10 · **Owner:** solo maintainer · **Status:** register
> complete; slices unplanned (each gets its own plan doc before execution,
> per house convention)
>
> **How to use:** This is a register, not an execution plan. When capacity
> frees up, pick a phase, write its detailed plan (verified state, design
> decisions, edge cases, acceptance tests — the gap-plan format), get the
> external review pass, then build. Items within a phase are ordered by
> pain; phase order is by urgency.

---

## 1. Where this fits

| Gap | Phase | Urgency | Why |
|---|---|---|---|
| No caching layer | H | High | First real traffic spike = timeouts |
| Sentry unwired + no structured logging | H | High | Blind to production errors |
| Image srcset not consumed on storefront | H | High | Variants generated but never served — mobile LCP suffering for nothing |
| No product reviews/ratings | I | High | Every competitor has this; merchants compare feature tables |
| No faceted search/filtering | I | Medium | Storefront feels basic on large catalogs |
| No storefront real-time updates | J | Medium | UX gap on order/payment confirmation |
| No 2FA on admin | → SECURITY_ROADMAP.md | High | Separate plan |
| No API versioning strategy | K | Planning | Becomes urgent with the extension ecosystem |
| No FK on payments→orders (and similar) | L | Low | Data integrity debt, not user-facing |

**Explicitly out of scope here** (tracked elsewhere or deliberate non-goals):
Security items → `docs/SECURITY_ROADMAP.md`. Abandoned carts, feeds, GDPR →
gap Phase D. Meilisearch → launch Phase 3. Multi-vendor → launch Phase 3.
Gift cards, carrier APIs → gap §5 non-goals.

---

## 2. Gap register (verified 2026-11-10)

### H.1 No caching layer
- **Verdict:** ABSENT — Redis runs for queues/Reverb only. No response
  caching, no config/route caching in the prod deploy path, no
  `php artisan optimize`. Public catalog endpoints hit the DB on every
  request.
- **Impact:** A moderate-traffic store will hit DB bottlenecks fast; the
  product list page is the most-hit endpoint and has zero caching.
- **Sketch:** Redis-backed response cache on public catalog/content
  endpoints (cache key = locale + currency + query params, TTL 60 s,
  flush on catalog write events). `php artisan config:cache route:cache`
  in the prod compose entrypoint. Consider a full-page CDN layer later.

### H.2 Sentry installed but unwired
- **Verdict:** PARTIAL *(revised 2026-11-10 after code verification)* — the
  SDK's exception handler was already wired (`bootstrap/app.php:28`,
  `SentryIntegration::handles()`), the config file was complete, and the
  prod compose passes the DSN through. The real gap was **missing context
  enrichment**: captured errors arrived without request ID, user, route,
  or breadcrumbs. Now wired: `SentryContextServiceProvider` tags every
  capture with the request ID + route + user ID (no PII), adds a request
  breadcrumb, and domain breadcrumbs on the checkout orchestrator and
  payment webhook handler. Tracing remains opt-in via
  `SENTRY_TRACES_SAMPLE_RATE` (currently 0). Inert without a DSN.
- **Remaining:** performance spans on checkout + webhook (flip the sample
  rate when you create the account).

### H.3 Image srcset not consumed
- **Verdict:** MOSTLY DONE *(revised 2026-11-10 after code verification)* —
  the product card already renders `srcSet` from variants (gap C.3's
  consumer), the product gallery uses variant-specific URLs, and the blog
  cards use srcset. The real remaining gap: cart/checkout page thumbnails
  use the original `image_url` (the Cart API doesn't expose
  `image_variants` on items — a small backend transformer change). Minor
  performance impact: thumbnails are lazy-loaded and displayed at 64px.
  (gap C.3, landed 2026-11-08) but the storefront's `<img>` tags don't
  render `srcset` from them. The variants exist on disk; nobody fetches them.
- **Impact:** Mobile LCP suffering for nothing — the bandwidth savings are
  generated but never delivered.
- **Sketch:** Extend the public Media resource to expose variant URLs.
  Storefront: a small `<ResponsiveImage>` component that reads the variant
  map and renders `srcset`/`sizes`. Audit the PDP and cart thumbnails.

### H.4 Structured logging + correlation IDs
- **Verdict:** PARTIAL — `AssignRequestId` middleware assigns a request ID
  but nothing consumes it downstream. `Log::info/error` calls use
  inconsistent context arrays.
- **Impact:** Debugging a production issue means grepping raw text logs
  with no thread to follow.
- **Sketch:** JSON logging channel in prod. Context enrichment: the request
  ID from the middleware automatically attached to every log line in the
  request lifecycle. Standardize context keys (order_id, payment_id, etc.).

---

### I.1 Product reviews & ratings
- **Verdict:** ABSENT — no module, no fields, no endpoints. Products have
  no rating_avg/rating_count (the SEO structured data already references
  these — they're always null today).
- **Impact:** Merchants comparing platforms see this as a missing table-stakes
  row. The storefront PDP has no social proof.
- **Sketch:** New `Review` module (or under Catalog): one review per user
  per product, verified-purchase badge (order exists with that product),
  rating 1–5, title + body, admin moderation queue. Product resource gains
  `rating_avg` + `rating_count` (computed, cached). Storefront: review list,
  star display, write form. ~3–5 d as a full slice.

### I.2 Faceted search & filtering
- **Verdict:** PARTIAL — the storefront has a text search box (SQL LIKE)
  and basic category browsing. No attribute-based filtering (size, color,
  price range), no multi-select, no sort by relevance.
- **Impact:** On a catalog of 50+ products, browsing becomes painful. Every
  competitor has faceted nav.
- **Sketch:** Backend: a filtered product-list endpoint accepting
  attribute-value filters (checkbox groups), price range, and sort options.
  Storefront: a filter sidebar that reads the available facets from the
  API. This is independent of Meilisearch (which solves *search*, not
  *filtering*) — the two compose later.

---

### J.1 Storefront real-time updates
- **Verdict:** PARTIAL — Reverb broadcasts to the admin panel (notifications,
  failed jobs). The storefront gets nothing; order/payment status requires
  a page refresh.
- **Impact:** "Payment received" and "Order confirmed" should push to the
  customer's browser. The checkout return page feels broken while polling.
- **Sketch:** Expose the existing order/payment events on a private Reverb
  channel keyed by user ID (sanctum-authenticated). Storefront: subscribe
  on the order-detail and payment-return pages, update status in place.

---

### K.1 API versioning strategy
- **Verdict:** ABSENT — everything is `api/v1/`. No deprecation policy,
  no version header, no plan for v2.
- **Impact:** Becomes urgent the moment a third-party extension depends on
  a v1 shape (the marketplace makes this real). Breaking a v1 endpoint
  breaks every installed extension.
- **Sketch:** A policy document, not code: URL-path versioning stays;
  additive changes within v1 are always safe; breaking changes = v2 path
  with a deprecation window. Route group prefix becomes a config constant.
  Sunset headers on deprecated endpoints.

---

### L.1 Cross-table FK integrity
- **Verdict:** PARTIAL — `payments.order_id` and several other cross-module
  references are plain columns with no FK constraint. A deleted order
  leaves orphaned payment rows.
- **Impact:** Data integrity debt, not user-facing. Becomes a real problem
  with the transfer/import module replaying rows in unexpected orders.
- **Sketch:** Audit all cross-table references; add FKs where the lifecycle
  is clear (payments→orders, refunds→payments). Defer where cross-module
  boundaries make it ambiguous (the module contracts may need to change first).

---

## 3. Policies this roadmap adds

1. **Every generated artifact gets consumed.** Shipping variant generation
   without srcset consumption (H.3) or SEO structured data referencing
   always-null ratings (I.1) is worse than not shipping it — it creates
   the appearance of a feature that doesn't work.
2. **Caching is a feature, not an optimization.** The first real store on
   Baxela will hit this wall; treating it as a later optimization means
   debugging timeouts under load instead of proactively shipping a cache.
3. **Observability before scale.** Sentry (H.2) and structured logging (H.4)
   land before any traffic-driving launch, not after the first incident.

## 4. Related docs

- `docs/SECURITY_ROADMAP.md` — security gaps (separate initiative, phases S.1–S.4)
- `docs/ROADMAP.md` — public-launch roadmap (Meilisearch, multi-vendor)
- `docs/PLATFORM_GAPS_ROADMAP.md` — gaps A–D (the lettered predecessor)
- `docs/EXTENSION_MARKETPLACE_ROADMAP.md` — extension system + marketplace
- `docs/plans/launch-readiness.md` — the launch gate this hardening feeds
