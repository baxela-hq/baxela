# Platform Gaps Roadmap — commerce & ops features the other plans don't cover

> **Purpose of this document:** Self-contained register and execution plan for the platform
> gaps that fall *between* `ROADMAP.md` (public launch → stars) and
> `EXTENSION_MARKETPLACE_ROADMAP.md` (extension system + marketplace). Created from the
> 2026-11-02 whole-repo gap analysis; every item below was checked against the code and is
> absent or partial there, and none of them is already tracked in the other two roadmaps.
>
> **Created:** 2026-11-03 · **Owner:** solo maintainer · **Status:** planned, not started
>
> **How to use:** Phases are **lettered (A→D)** so they never collide with the numbered
> phases of the other two roadmaps. Initiative order stays as decided: launch work first,
> extension Phase 0–1 may interleave anytime; this roadmap's Phase A rides along with
> launch, and Phases B–D become the release-cadence backlog (ROADMAP §3.1). Re-verify any
> fact marked ⚠ against the codebase before relying on it — the code moves. Tick
> checkboxes and note dates. Repo conventions: conventional commits (`type(scope): …`),
> never commit unless explicitly asked.

---

## 1. Where this fits

| Gap | Phase | Target release | Why then |
|---|---|---|---|
| Per-gateway refund claim footnote | A | alpha.1 launch assets | Honesty discipline — same rule as the multi-currency wording |
| Backup & restore story | A | alpha.1–alpha.2 | Self-hosters' stores are one disk failure from gone |
| Health-check endpoint | A | alpha.2 | Trivial; enables uptime monitoring docs |
| Invoices / credit notes / PDF | B | beta gate | Legally required for merchants; nothing exists today |
| hreflang + structured data | B | beta gate | Storefront's organic-traffic value; cheap |
| Queue visibility | B | beta gate | Ops blindness on the prod stack |
| Gateway refund coverage (4 drivers) | B | beta → v0.2 | "8 gateways" implies refundability |
| Regional + inclusive tax / VAT ID | C | v0.2–v0.3 | Required by the West-facing, geography-neutral strategy |
| Outbound webhooks + API tokens | C | v0.3 | Complements the in-process extension system; serves mobile/ERP |
| Image variants | C | v0.2–v0.4 | Storefront LCP; no processing exists today |
| Abandoned cart, back-in-stock, feeds, GDPR, admin tests | D | v0.3+, demand-driven | Growth layer on machinery that already exists |

**Interaction with the extension roadmap:** the `tax-engine` and `shipping-carrier`
extension types (marketplace doc §3) only work if core owns clean contracts — C.1 and any
future carrier work must land *in core first*, extension-izing comes later (Phase 6 there).
Outbound webhooks (C.2) are the out-of-process complement to in-process extensions.

## 2. Gap register (verified 2026-11-02)

| # | Gap | Verdict | Evidence |
|---|---|---|---|
| 1 | Tax is country × class, exclusive-only — no regional (US state / province) rates, no tax-inclusive pricing, no VAT-ID handling | PARTIAL | `Modules/Tax` (TaxClass + TaxRate country-scoped); `docs/THIRD_PARTY_SERVICES.md` |
| 2 | No invoices, credit notes, packing slips, or any PDF document generation | ABSENT | `Modules/Order` contents — orders/items/addresses/RMA/stats only |
| 3 | Refunds work on 3 of 8 gateways; other five throw `CannotRefund` (Adyen Pay-by-Link, MercadoPago, Checkout.com, Razorpay, NowPayments) | PARTIAL | `Modules/Payment/.../Gateways/Drivers/` |
| 4 | No outbound webhooks for merchants (order/shipment/refund → external systems); only inbound gateway + Telegram webhooks. No server-to-server API tokens | ABSENT | `Modules/*/routes/api/webhook.php` (inbound only) |
| 5 | Queue runs on the **database driver** ⚠; no Horizon, no failed-job surface. No backup command or documented restore | ABSENT | `config/queue.php` ⚠; no backup package in composer.json |
| 6 | SEO: per-locale meta + slugs + sitemaps exist; no hreflang alternates, no Product JSON-LD / BreadcrumbList / Organization ⚠ | PARTIAL | `ProductSeoTranslation.php`, `ListSitemapAction.php` |
| 7 | Inventory: single stock per variant — no reservations, backorders, low-stock thresholds, multi-location | PARTIAL | `Modules/Inventory` (single `InventoryStock`) |
| 8 | Media: originals only, no thumbnail/responsive-variant generation ⚠ | PARTIAL | `Modules/Media` |
| 9 | No abandoned-cart recovery, no back-in-stock notifications, no product feeds (Google Shopping/Meta) | ABSENT | — |
| 10 | No GDPR data-subject tooling (export/erasure, consent log) ⚠ | ABSENT | — |
| 11 | Admin panel: zero tests; storefront: 1 e2e spec | PARTIAL | `apps/admin` (no test runner), `e2e/happy-path.spec.ts` |
| 12 | No health-check endpoint exposed through the prod stack ⚠ | ABSENT | — |

---

## Phase A — honesty & survival kit (rides along with launch; alpha.1–alpha.2)

### A.1 Refund-coverage claim footnote (hours)

The comparison table (launch-readiness Part F) says "8 payment gateways with webhook
verification" — true, but merchants read that as refundability. Same rule as the
multi-currency wording: never the bare claim without the footnote.

- [x] README + comparison table + THIRD_PARTY_SERVICES.md: add
      "Refunds: Stripe, PayPal, Manual built-in; Adyen/MercadoPago/Checkout.com/Razorpay
      via merchant dashboard (driver support roadmaped); NowPayments (crypto) has no
      provider-side refund." *(done 2026-11-03 — footnote wording baked into
      launch-readiness Part F §2; THIRD_PARTY_SERVICES already accurate, gained the
      code-link note; root README had no bare claim to fix)*
- [x] Admin payment-methods page: show refund capability per driver (i18n, 6 locales).
      *(done 2026-11-03 — plus the payments page: "Issue refund" now disables with a
      tooltip on non-refundable drivers; see `docs/plans/gap-a1-refund-claims.md`)*

Suggested commit: `docs(monorepo): state per-gateway refund coverage honestly`

### A.2 Backup & restore story (a day)

Self-hosters on `docker-compose.prod.yml` and shared hosting currently have nothing. A
store that can't be restored is a liability the README shouldn't create.

- [ ] Install `spatie/laravel-backup` (battle-tested; pruning; failure alerts — routes
      alerts into the existing Notification channels rather than inventing new ones).
- [ ] `baxela:backup` / `baxela:restore` command docs; nightly schedule **opt-in** via a
      setting, disabled by default (don't surprise small hosts with disk fill).
- [ ] Store backups on the configured media disk (local or S3) with retention config.
- [ ] Docs: restore drill for prod compose AND shared hosting (file + DB steps).
- [ ] Test: backup runs against `Storage::fake`, fails loudly when DB unreachable.

Suggested commit: `feat(backend/core): add scheduled backup and restore tooling`

### A.3 Health check (an hour)

- [ ] Verify Laravel's `/up` passes through nginx in the prod compose stack ⚠; if not,
      add an `api/v1/health` action checking DB + Redis + queue liveness.
- [ ] Document an uptime-monitor setup line in the deployment docs.

Suggested commit: `feat(backend/core): expose stack health check for uptime monitors`

**Phase A exit criteria:** every gateway claim carries its refund footnote; a documented,
tested backup/restore path exists for both deploy targets; an unauthenticated health
endpoint is reachable on the public URL.

---

## Phase B — merchant-blocking documents & visibility (beta gate)

### B.1 Invoices & credit notes (day-sized, the big one)

- [ ] `invoices` table: sequential number per store (setting for prefix/format, e.g.
      `INV-2026-0001`), `order_id`, `issued_at`, status (issued/void), and a **totals +
      billing-address snapshot** (orders already snapshot tax — mirror that; documents must
      never drift from the order they were cut from).
- [ ] Issue automatically on payment success (listener on the existing order-paid domain
      event ⚠ name); reissue/void as guarded admin actions.
- [ ] `credit_notes` table linked to `PaymentRefund` rows — cut on refund success,
      referencing the original invoice.
- [ ] PDF rendering via `barryvdh/laravel-dompdf` — pure PHP, no wkhtmltopdf binary, keeps
      the shared-hosting path viable. Blade templates localized through the existing
      per-locale template machinery.
- [ ] Admin: list/filter/download/void. Storefront: download from profile → orders.
      Notification: email the invoice on issue (attachment).
- [ ] Tests: number sequence under concurrency, snapshot immutability, credit-note totals
      match refund. Bruno + admin i18n (6 locales).

Suggested commits: `feat(backend/order): invoice and credit note documents`,
`feat(admin): invoice management`, `feat(storefront): invoice downloads in profile orders`

### B.2 hreflang + structured data (cheap, high-leverage)

- [ ] Storefront `generateMetadata`: canonical + `alternates.languages` for all 6 locales
      on every static route, PDP, category, brand, blog post.
- [ ] JSON-LD: `Product` (offers, `aggregateRating` from the existing
      `rating_avg`/`rating_count`), `BreadcrumbList`, `Organization`, blog `Article`.
- [ ] Sitemap: per-URL `xhtml:link` hreflang alternates ⚠ (verify sitemap shape first).
- [ ] Validation: Rich Results test on the demo PDP added to launch-QA checks; a small
      assertion script or e2e step so it can't silently regress.

Suggested commit: `feat(storefront): hreflang alternates and product structured data`

### B.3 Queue visibility (half a day)

- [ ] Switch prod compose queue connection to `redis` (Redis already runs for
      Reverb/cache); dev may stay on `database`.
- [ ] Install Horizon, add a service to dev + prod compose, protect its dashboard
      (admin-auth gate or bind to localhost-only behind nginx — decide at implementation).
- [ ] Prune/monitor: `queue:prune-failed` schedule, failed-job alert into the Notification
      module.
- [ ] Fallback if Horizon feels heavy: a failed-jobs table + minimal admin page reusing
      the AuditLog list pattern. Pick one, don't build both.

Suggested commits: `feat(infra): run horizon on redis queue with alerting`,
or `feat(backend/core): failed jobs admin surface`

### B.4 Gateway refund coverage (staged: ~2 drivers per release, beta → v0.2)

- [ ] Adyen + Checkout.com refunds via their REST APIs (`Http::fake` tests, mirror the
      Stripe driver's two-phase refund flow).
- [ ] MercadoPago + Razorpay refunds likewise.
- [ ] NowPayments: document as provider-impossible (crypto) — admin UI states it plainly
      instead of a generic failure.
- [ ] When each lands: shrink the A.1 footnote; release note the driver name.

Suggested commit: `feat(backend/payment): adyen and checkoutcom refund drivers`

**Phase B exit criteria:** every paid order can produce an invoice/credit note PDF;
PDP passes Google Rich Results; a failed job is visible and alerts; refunds work or are
explicitly documented for every enabled gateway.

---

## Phase C — engine work (v0.2–v0.4; design before building, additive schemas)

> **Policy hook:** once a third-party extension is listed, migrations become
> additive-only (marketplace doc §5.2). C.1 and C.2 shape extension-facing contracts —
> design them with the extension system's registry pattern in mind.

### C.1 Tax engine: regional, inclusive, VAT (the strategic one — split, don't batch)

- [ ] **C.1a Regional rates:** extend `TaxRate` scope from country to
      country + region (ISO 3166-2), with a country-wildcard default. Seed region data
      into Core the way countries/currencies are seeded (`database/data/`). ⚠ First
      verify the `Address` schema has a region/state field — if not, that additive
      migration comes first, storefront address forms second. US state rates ship as seed
      data; AvaTax/TaxJar are explicitly *not* core (future `tax-engine` extension).
- [ ] **C.1b Tax-inclusive pricing:** `price_contains_tax` flag on TaxRate/TaxClass;
      display-price resolution in the storefront (Money utility stays integer-exact);
      orders snapshot the contained-tax amount alongside the existing tax snapshot.
- [ ] **C.1c VAT ID (B2B):** VAT-ID field on customer/address, offline checksum
      validation per country format, reverse-charge zero-rating with the VAT ID recorded
      as evidence on the order. VIES online check = later extension, not core.
- [ ] Tests: region precedence over country default, inclusive/exclusive rounding,
      reverse-charge evidence snapshot. Docs: a `docs/tax.md` honest about what core does
      and doesn't cover.

Suggested commits: `feat(backend/tax): region-scoped tax rates`,
`feat(backend/tax): tax-inclusive pricing support`,
`feat(backend/tax): vat id validation and reverse charge`

### C.2 Outbound webhooks + server-to-server API tokens (v0.3)

- [ ] `webhook_endpoints` table (URL, subscribed events, secret, active); HMAC-SHA256
      signature header; delivery log with response status; retries with backoff on the
      queue; auto-disable after N consecutive failures (alert the merchant).
- [ ] v1 event catalog mapped from existing domain events: `order.created`,
      `order.paid`, `order.cancelled`, `shipment.dispatched`, `refund.processed`,
      `return.*` ⚠ (verify exact event names in `Modules/Core/app/Contracts/`).
- [ ] Admin UI: endpoint CRUD, delivery log viewer, "send test event" button.
- [ ] API tokens: sanctum tokens with abilities + per-token rate limit for
      server-to-server consumers (mobile app, ERP); document scopes.
- [ ] Tests: signature verification, retry/backoff, dead-lettering, ability enforcement.

Suggested commits: `feat(backend/core): outbound webhook endpoints and deliveries`,
`feat(admin): webhook endpoint management`

### C.3 Media image variants (v0.2–v0.4)

- [ ] Generate thumbnail/responsive sizes on upload via `intervention/image` (pure PHP —
      shared-hosting safe); conversions run on the queue.
- [ ] Size presets in a core config; `MediaResource` exposes variants; storefront renders
      `srcset`. Originals untouched; variant files tracked for deletion with the parent.

Suggested commit: `feat(backend/media): queued image variant generation`

**Phase C exit criteria:** a US store taxes correctly by state; an EU store shows and
charges VAT-inclusive with B2B reverse charge; merchants can receive signed order
webhooks; PDP images ship responsive variants.

---

## Phase D — growth layer (v0.3+; pick by demand, not by sequence)

- [ ] **D.1 Abandoned-cart recovery.** Scheduled command flags carts idle > threshold;
      v1 targets logged-in users (guest carts lack an email until checkout ⚠ — note the
      limitation honestly); notification template + admin toggle; rate-limit to one email
      per cart per window. Builds directly on the 4-channel Notification module.
- [ ] **D.2 Back-in-stock subscriptions.** Subscribe on OOS PDP (guest email or user);
      trigger off the inventory restock event; dedupe; auto-expire after send.
- [ ] **D.3 Product feeds.** Google Shopping XML per locale/currency, scheduled
      regeneration, admin toggle; Meta catalog feed after.
- [ ] **D.4 GDPR DSR.** Self-service data export (JSON/zip) and account erasure with
      order-retention carve-outs (anonymize personal fields, keep financial records);
      audit-log every DSR action; storefront cookie-consent banner + consent log.
- [ ] **D.5 Admin test slice.** Playwright against the dev compose (no new framework):
      sign-in + product create/edit + order view + one permission-denied path. Small on
      purpose — a regression net, not coverage theater.

Suggested commits: `feat(backend/cart): abandoned cart recovery`,
`feat(backend/user): back-in-stock subscriptions`,
`feat(backend/catalog): google shopping feed`,
`feat(backend/user): gdpr data export and erasure`,
`test(admin): playwright smoke slice`

---

## 5. Explicit non-goals / deferred (write these down; silence reads as oversight)

- **Multi-warehouse / stock reservations / backorders** — until a real store asks.
- **Gift cards** — later; coupons + promotions cover v1 merchandising.
- **AvaTax / TaxJar engines** — `tax-engine` *extensions*, never core (geography-neutral).
- **Carrier APIs / labels / live tracking** — future `shipping-carrier` extension type;
  core keeps table rates + manual tracking until then.
- **E-invoicing formats** (Factur-X, ZUGFeRD, Iranian Moadian) — B.1 ships PDF documents,
  not fiscal e-invoicing compliance; per-region formats belong to extensions.
- **Multi-store / multi-tenant single install** — revisit only if the star-growth data
  says merchants want it (competitor table says Bagisto owns that niche).

## 6. Policies this roadmap adds

1. **Refundability is part of a gateway claim.** Any gateway advertised gets either a
   working refund driver or a named footnote — permanently, not just at launch.
2. **Documents are snapshots.** Invoices/credit notes copy order state at issuance; they
   never re-derive totals from live order rows.
3. **Tax correctness over tax breadth.** Core does regions + inclusive + VAT ID well;
   country-specific engines are extension territory. This mirrors the geography-neutral
   decision #1 in the marketplace roadmap.
4. **Self-hosting guarantees:** every deploy target Baxela ships (prod compose, shared
   hosting) must have a documented backup AND restore path that is actually tested.

## 7. Related docs

- `docs/ROADMAP.md` — public-launch roadmap; its Phase 3 §3.1 release cadence is the
  vehicle this roadmap's B–D phases ship on. §3.5 (multi-vendor decision) stays there.
- `docs/EXTENSION_MARKETPLACE_ROADMAP.md` — extension system; C.1/C.2 here pre-shape its
  contracts; its §5 policies (additive migrations, semver surface) bind Phase C onward.
- `docs/plans/launch-readiness.md` — Part F owns the comparison table this roadmap's
  A.1 footnote lands in.
- `docs/THIRD_PARTY_SERVICES.md` — integration inventory; update alongside A.1 and C.2.
- `apps/backend/AGENTS.md` — module conventions (actions, webhooks, lang files) every
  task above must respect.
