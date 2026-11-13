# Plan gap-B1 — Invoices, credit notes & PDF documents (v2)

> **Purpose of this document:** Elaborate the gap (what these documents *are*, why the
> platform needs them) and serve as the execution plan for Phase B task **B.1** of
> `docs/PLATFORM_GAPS_ROADMAP.md`. The primer (§1) stands alone — read it to understand
> the domain; the rest is build order.
>
> **Created:** 2026-11-03 · **Revised:** 2026-11-07 after an external plan review
> (8.5/10 — "architectural corrections, not a redesign"). All seven priority findings
> are incorporated; where this v2 goes beyond the review, it says so (see §3-D10 — the
> review's event fix wouldn't have covered partial refunds; verified in code).
>
> **Status:** **approved 2026-11-07** (final review 9.4/10 — four clarifications folded
> in as v2.2, see §10) — **implementation started** · **Size:** review-calibrated —
> backend kernel + PDF PoC ≈ 2–3 days; the complete plan incl. admin/storefront
> surfaces, localization, email ≈ 1–2 weeks (five PR-sized phases; each lands green).
>
> **How to use:** Phases strictly in order (kernel → rendering → automation → credit
> notes → polish). Re-verify ⚠-marked facts before relying on them. Conventional
> commits; never commit unless asked. Heavy commands `nice -n 10`; scope test runs to
> `Modules/Order`.

---

## 1. Primer — what these documents actually are

### 1.1 Invoice (صورتحساب فروش)

The **commercial record of a sale**. Once an order is paid, the merchant issues an
invoice that says: *who sold what to whom, for how much, on which date, under which
number.* What goes on one:

- **Sequential number** — `INV-2026-000123`. Sequential and never reused; voided
  documents keep their numbers. (Numbering requirements differ per jurisdiction — see
  D3; region-specific rules belong to future extensions.)
- **Issue date** and (if different) **delivery/payment date**.
- **Seller identity** — legal name, address, tax ID of the store (frozen at issue).
- **Buyer identity** — billing name/address/email from the order (B2B VAT ID: future,
  with gap roadmap C.1c).
- **Line items** — product name, quantity, unit price, per-line discount, per-line tax
  rate and amount.
- **Totals block** — subtotal, order discount (coupon), shipping cost, shipping tax,
  total tax, grand total; charged currency.
- Payment reference (order code, payment method, transaction id).

Two rules make an invoice an invoice:

1. **Immutable once issued.** You never edit an issued invoice — it preserves what was
   actually sold, independent of the current state of products, prices, addresses, tax
   configs, coupons, or store settings. Mistakes are corrected by voiding and
   reissuing, or by a **credit note**. The original stays in the books.
2. **Self-contained.** It shows its own numbers, not "whatever the order says now."

### 1.2 Credit note (قبض بازگشتی) — the "negative invoice"

An **accounting document that adjusts a previously issued invoice**, always
**referencing** it. It has its own sequential number (`CN-2026-000045`), the original
invoice's number, and an amount. The invoice's outstanding balance = total − sum(its
active credit notes). Bookkeeping stays symmetric: sale documented up, adjustment
documented down, both traceable.

**Refund ≠ credit note.** A refund is a *money movement* (the ledger's job); a credit
note is an *accounting adjustment* (this feature's job). They coincide in the common
case, but not always (goodwill refunds, price corrections without money movement,
cancelled orders before invoicing). Baxela v1 adopts a **documented default policy** —
one credit note per finalized refund — without hard-wiring it into the domain
(see D5). Manually issued credit notes are a planned follow-up, not v1.

### 1.3 Receipt vs packing slip (context, both out of scope here)

- **Receipt** = proof *payment happened*. In e-commerce, an invoice stamped **PAID**
  (which ours always is — issued on payment) doubles as the receipt. Nothing extra to build.
- **Packing slip** = warehouse pick/pack list, no prices. Deferred to the carrier work
  (gap C); Baxela has no warehouse/pick flow yet.

### 1.4 Worked example

```
Order #1024, paid EUR 100.00 (items 80 + shipping 8 + tax 12)
  → Invoice INV-2026-000001 issued          total 100.00, balance 100.00
Customer returns one item, admin refunds 40.00 (refund ledger finalizes RF-uuid-A)
  → Credit note CN-2026-000001 issued       references INV-2026-000001, amount 40.00
Invoice view now shows:                     total 100.00, credited 40.00, balance 60.00
Second refund 60.00 → CN-2026-000002 → balance 0.00 (fully credited — mirrors REFUNDED)
```

### 1.5 Why Baxela needs this (and why it's in the beta gate)

- **Legal:** most jurisdictions require merchants to provide invoices on demand; B2B
  customers cannot book expenses or reclaim VAT without one.
- **Auditability:** sequential, never-reused numbers with retained voided documents are
  what a tax audit asks for first.
- **Product completeness:** every competitor's order page has a "Download invoice"
  button; its absence reads as unfinished, especially next to an 8-gateway checkout.
- **Refund symmetry:** Baxela already has a rigorous refund ledger
  (`payment_refunds`, two-phase, reconciliation states) — but no *document* comes out
  of it. The books move; the paper doesn't exist.
- **Geography-neutral by design** (marketplace roadmap decision #1): core ships
  *generic* PDF documents behind a renderer abstraction (D12). Country-specific
  e-invoicing (Factur-X/XRechnung, Iranian Moadian/سامانه مودیان) are future
  **extensions**, not core — see §8.

## 2. Verified current state (2026-11-07, post-transfer/ERD tree)

**Everything needed as *input* exists except the events below; the document layer is absent.**

| Input needed | Exists | Evidence |
|---|---|---|
| "Order paid" hook | ✅ | `Modules/Core/app/Contracts/Events/Order/OrderPaidEvent.php` — carries `id`, `user_id`, `order_code`, `total_amount`, `locale`; fired from `OrderGateway::markAsPaid()`; Notification module already listens (listener + `EventServiceProvider` pattern to mirror) |
| **Per-refund hook** | ❌ **GAP** | `OrderRefundedEvent` fires from `OrderGateway::applyRefund()` **only when the order crosses to fully REFUNDED** (`OrderGateway.php:295`); partial refunds (→ `PARTIALLY_REFUNDED`) fire nothing. The event also carries no refund identity. Fix: new `RefundFinalizedEvent` (D10) |
| Line-item snapshots | ✅ | `OrderItemSchema`: `product_name_snapshot`, `product_slug_snapshot`, `price_snapshot`, `base_price`, `promotion_discount`, `promotion_id`, `quantity`, `tax_amount`, `tax_rate`, `tax_class_id`, `image_url`. **No SKU** — source from the variant relation at issue time (nullable), never re-derive later |
| Totals snapshots | ✅ | `OrderSchema`: `total_amount`, `tax_amount`, `shipping_cost`, `shipping_tax_amount`, `shipping_tax_rate`, `discount_amount`, `coupon_code`, `refunded_amount`, `currency_id`, display-currency + FX snapshot |
| Billing address | ✅ | `OrderAddress` with billing/shipping type enum |
| Refund ledger (CN source) | ✅ | `PaymentRefundSchema`: `uuid`, `order_id`, `amount`, `reason`, `status`, `transaction_id`, `refunded_by` |
| **Money as minor units** | ✅ precedent | `OrderGatewayInterface::applyRefund(int $orderId, int $refundedTotalMinor, int $paidTotalMinor)` — the contract already crosses module boundaries in integer minor units; D11 extends this discipline |
| Email machinery | ✅ | Notification module, per-locale templates (`LocaleTemplateEngine`), 4 channels |
| File storage | ✅ | Media module disks: local + S3, disk-aware URLs |
| Settings pattern | ✅ | Setting module enum-driven (`SettingNameEnum`); store timezone setting exists (applied at boot — `config('app.timezone')` is the store's TZ, which D3 uses for document years) |
| PDF capability | ❌ | No PDF package in composer — clean slate |
| Invoice/CN models, numbering, admin/storefront surfaces, email attachments | ❌ | Absent everywhere |

## 3. Design decisions

| # | Decision | Why |
|---|---|---|
| D1 | **Home: Order module** (like `OrderReturn`). No new module. | Documents are order lifecycle; events provide the Payment→Order integration boundary. |
| D2 (v2.1) | **Multiple invoices per order, one ACTIVE at a time.** `revision` int (1, 2, …); unique `(order_id, revision)`; unique `number`. Issue = one transaction: **lock the parent Order row** (`lockForUpdate`) — locking existing invoice rows cannot protect an empty result set — then assert no ACTIVE invoice, compute the next revision, allocate the number, snapshot, insert. Uniques remain the final DB-level protection; the same order-row lock covers void/reissue. Void keeps the row and number; reissue takes the next revision. **Business rule (explicit):** a replacement invoice is a *new legal document*, not an automatic transfer of the original's accounting position — V1 implements **no balance transfers**; relating old credit notes to a replacement is a manual correction workflow with audit history. Refund-backed auto-CN is **never** issued against a voided invoice (Phase 4 guard). | Fixes the v1 contradiction and the empty-result-set race (review §2.1, §2.3). |
| D3 (rev) | **Sequential numbering, atomically allocated, never reused:** `{prefix}-{year}-{6-digit seq}` per type per year (`INV-2026-000001`). Dedicated `order_document_sequences` row per (type, year): first allocation creates it under a lock (upsert-or-lock pattern so two simultaneous first allocations can't both initialize); subsequent allocations `SELECT … FOR UPDATE` inside the issue transaction. **Year boundary from a store-timezone resolver** — a small explicit resolver whose v1 implementation reads `config('app.timezone')` (the Setting-module store TZ applied at boot); the numbering boundary never reads the config directly, so a future multi-store/tenant process cannot silently use the wrong TZ (review §3.3). Prefix is cosmetic at rendering time and stored on the document — changing the prefix setting affects only future numbers. Voided documents keep their numbers; no number is ever re-issued. Numbering policy documented in the flow doc; jurisdiction-specific rules = future extensions. | Gapless-per-sequence is not a universal legal requirement — sequential, atomic, no-reuse is the defensible core. The dedicated sequence table (not auto-increment IDs) stays. |
| D4 | **Snapshot immutability**, now fully explicit: at issue time copy billing **address + buyer identity** (name/company, email), **seller identity** (legal name, registered address, tax/VAT id, contact — from settings), line items (into `order_invoice_items`), and the totals block. Documents never join live order rows, order items, or current settings for money. | §1.1 rule 2; the review's §3.3. Seller reads settings **once, at issue**. |
| D5 (rev) | **One credit note per finalized refund is a documented default policy, not a domain assumption.** Refund ledger = money movement; credit note = accounting adjustment. CN references an invoice (required) and optionally a refund (`refund_uuid` nullable, unique when set — the idempotency key). The auto-CN listener implements the policy; manual CN issuance is a planned follow-up iteration (merchants correcting an invoice without moving money). Guard: Σ active CNs ≤ invoice total — enforced under an invoice-row lock (D14); failed guard = loud failure + admin alert, never silent. | The review's §2.4. Keeps the automation without foreclosing manual/one-off adjustments. |
| D6 | **PDF engine: `mpdf/mpdf`**, Blade templates, per-locale. Proof-of-concept gate in Phase 2 on real fa/ar output: character shaping, RTL + mixed-direction text, numerals vs Latin document numbers, currency symbols/decimals, long product names, multi-page pagination. If the PoC fails, dompdf is the fallback — **but the PoC decides, not the plan.** | Pure PHP — shared-hosting safe. RTL is the flagship-locale requirement. |
| D7 (v2.2) | **Issuance ≠ generation.** Issuing a document allocates the number and freezes the snapshot in one transaction — **no PDF, no remote I/O inside that transaction.** The PDF is produced through an explicit generation lifecycle on the row: `pdf_status ∈ pending_generation → generating → ready | generation_failed`. Synchronous generation right after commit for v1 (acceptable per review §3.1); a failure marks `generation_failed` — retry reuses the **same immutable snapshot and number**, never allocating a second number. Download endpoints serve only `ready` documents. **Two distinct recovery operations (review §2.4):** (1) *retry of an initial generation failure* — an explicit admin capability, audit-logged; (2) *regeneration of a `ready` document / corrupted-artifact recovery* — ops-only via artisan, checksum-verified, never an admin button. **Stuck `generating`:** the generation claim is an atomic CAS (`pending_generation → generating` + `generation_claimed_at` lease); a scheduled sweep returns rows whose lease expired back to `pending_generation`, so a crashed process cannot wedge a document. | A failed S3 upload must not lengthen a locked transaction; a failed render must not double-allocate numbers; customers must never download an incomplete document; a crashed worker must not wedge a document. |
| D8 | **Void, never edit** (invoices and credit notes): guarded admin action; number retained; audit-logged. A voided invoice can be superseded by a reissue (next revision, D2). | §1.1 rule 1; numbers never reused (D3). |
| D9 | **Auto-issue is a setting, default on** (`invoices.auto_issue`); admins can also issue/reissue manually from the order. | Sensible default; escape hatch for corrections. |
| D10 (new) | **New `RefundFinalizedEvent`** (Core contracts, Payment domain) — fired **once per successful refund finalization, after the finalize transaction commits**, carrying `refund_uuid`, `order_id`, `order_code`, `amount` (minor units), `currency_code`, `reason`, `locale`. Never fired for pending/failed/reconciliation-state refunds. **Not** an extension of `OrderRefundedEvent`: that event is an order-status transition (notifications keep listening to it), fires only on the full-refund crossing, and carries no refund identity — a per-refund CN listener on it would miss every partial refund (verified: `OrderGateway.php:295`; see §2). The refund identity flows Payment → event; the CN listener needs nothing else. | The review's blocker 2.1, taken further than the review proposed: per-refund semantics require a per-refund event, not per-order-status. |
| D11 (new) | **Money boundary, explicit:** domain + contract arithmetic in **integer minor units** via the Core `Money` utility (precedent: `applyRefund(...Minor)`); DB storage follows the existing decimal convention (⚠ match `refunds.amount` style); PDF renders formatted decimals. **Never floating-point arithmetic.** Every conversion site is one of exactly three: repository in (decimal→int), repository out (int→decimal), template render (int→formatted string). | The review's §3.4; the codebase already crosses this boundary in the refund ledger — codify it. |
| D12 (new) | **Renderer abstraction from day one:** `DocumentRendererInterface` (Core or Order contracts — `render(OrderInvoice|OrderCreditNote): PdfArtifact`) with exactly one implementation, `MpdfRenderer`/Generic PDF provider. Templates and mpdf stay behind it. **`PdfArtifact` is a readonly DTO** (`contents`, `mimeType`, `templateVersion`); the renderer only renders — a separate storage service persists the artifact, computes the SHA-256 checksum, and advances `pdf_status` (review §3.1). No provider registry, no marketplace wiring yet. | The review's strategic recommendation: don't couple the document lifecycle to mPDF or any country's rules; country providers (EU e-invoicing, Moadian) become extensions implementing the same interface. One interface + one driver — no premature framework. |
| D13 (new) | **Document integrity fields** on both documents: `pdf_generated_at`, `pdf_checksum` (SHA-256 of the stored bytes), `template_version`. | Cheap auditability + DR verification (review §3.2). |
| D14 (new) | **Credit-note issuance is transactional and locked:** inside one transaction — lock the invoice row (`FOR UPDATE`), compute Σ active CNs in minor units **inside** that lock, validate new amount ≤ remaining balance, allocate the number, insert. Voided CNs keep their rows and numbers; their amounts are excluded from the credited balance (the adjustment was undone), and the history remains visible. | The review's §3.6 double-refund race (€50 balance, two €30 refunds). Mirrors the refund ledger's row-lock discipline. |
| D15 (v2.2) | **Event reliability — honest guarantee: at-least-once with a documented crash window.** `RefundFinalizedEvent` dispatches after the finalize transaction commits; the CN side effect is idempotent (unique `refund_uuid`), so duplicates are safe. An after-commit dispatch does **not** survive a process crash between commit and dispatch — v1 closes that window with **reconciliation, not an outbox:** artisan `documents:reconcile-refunds` finds finalized refunds whose `refund_uuid` has no credit note and runs the same idempotent CN path (scheduled daily + ops-runnable). A transactional outbox is deferred until a second event consumer exists (§8) — one consumer doesn't justify the infrastructure yet; the reconcile command is deterministic and doubles as an ops tool. Invoice/CN issuance independently idempotent (invoice: one-ACTIVE under the order-row lock, D2); failed listeners retry safely without double-allocating numbers (numbers allocated inside the same transaction as the insert, D3/D14). | The final review's P0 §1.1: an un-delivered refund event must never mean a missing accounting document — reconciliation guarantees eventual completeness without new infra. |
| D16 (new) | **The document record is not the PDF.** Three layers, kept distinct: the immutable **financial record** (snapshot rows — never hard-deleted through ordinary application operations), its **representation** (the PDF artifact — regenerable under the D7 lifecycle, checksum-verified), and the **storage location** (disk/path; failures observable). Recovery regenerates representations and never mutates snapshots; numbers are never reused; void is always audited. | The review's §4 — the foundation claim for future accounting/e-invoicing integrations. |

## 4. Schema sketch (v2 — supersedes v1's three-table sketch)

```
order_invoices
  id, order_id, revision, number (unique), status [active|void],
  issued_at, voided_at, void_reason,
  currency_code,
  subtotal_amount, discount_amount, coupon_code, shipping_amount,
  shipping_tax_amount, shipping_tax_amount_rate?, tax_amount, total_amount,
  billing_address (json), buyer_identity (json), seller_identity (json),
  meta (json: order_code, payment method/txn refs),
  pdf_status [pending_generation|generating|ready|generation_failed],
  pdf_disk, pdf_path, pdf_checksum, pdf_generated_at, template_version
  UNIQUE(order_id, revision); UNIQUE(number)
  → credited_amount is DERIVED (Σ active CNs), balance = total_amount −
    Σ active CNs — computed in ONE dedicated document service that owns the
    currency + minor-unit conventions; never cached in a column until a
    consistency strategy exists (review §2.6)

order_invoice_items
  id, invoice_id, product_name, product_slug, sku (nullable — from variant
  at issue time), variant_id (nullable), quantity, unit_price,
  promotion_discount, coupon_discount_allocated (nullable ⚠ — copy it ONLY
  if order items persist a per-line coupon allocation; if not, the coupon
  lives in the invoice totals block, exactly where the order stores it —
  never invent an allocation at issue time), discount_amount
  (= promotion_discount + coupon_discount_allocated), tax_class_id,
  tax_class_name (label snapshot — the invoice stays readable without a
  live tax-class join), tax_rate, tax_amount, line_total,
  meta (json: image_url)
  → line semantics (minor units, D11), v2.2: **tax_amount is COPIED from
    the order-item snapshot — checkout's finalized rounding and allocation
    decisions are authoritative; the invoice never recalculates tax at
    issue time.** unit_price = the per-unit price actually charged before
    line-level promotion (from price_snapshot); line_total = the order
    item's finalized line total, copied like tax. No derived-then-rounded
    recomputation anywhere in issuance.

order_credit_notes
  id, invoice_id, refund_uuid (nullable, unique when set), number (unique),
  status [active|void], issued_at, voided_at, void_reason,
  currency_code, amount, reason,
  pdf_status, pdf_disk, pdf_path, pdf_checksum, pdf_generated_at,
  template_version
  (CN line-item breakdown deferred — see §8)

order_document_sequences
  id, type [invoice|credit_note], year, last_number
  UNIQUE(type, year)
```

(Decimal columns follow the existing `refunds.amount` style ⚠; arithmetic in minor
units per D11. Exact column style follows existing Order migrations.)

## 5. Work items

### Phase 1 — Document kernel (backend, no rendering) — *includes the full lifecycle design*

> **DONE 2026-11-07.** Kernel shipped: 4 migrations (verified on SQLite), 8
> schema/enum classes, 4 models with relations, `StoreTimezone` resolver,
> `DocumentNumberService` (first-row-safe via blocked-insert + unique-catch),
> `IssueInvoiceAction` (parent-order lock, PAID/PARTIALLY_REFUNDED/REFUNDED guard,
> full D4 snapshot — tax/line totals COPIED from order items, minor-unit math via
> the Core `Money` utility), `VoidInvoiceAction` (order-row lock, invalid-transition
> guard), `DocumentBalanceService` (the single credited/balance calculation site),
> admin list/show/issue/void endpoints + resources (money formatted as decimals),
> error codes + 6-locale lang files, **14-test InvoiceTest** (guest 401, permission
> 403, snapshot assertions, idempotent double-issue, ineligible unpaid, lazy
> partial-refund issuance, cross-order sequence, prior-year isolation, void+reissue
> revision 2, double-void guard, 422 validation, 404, filters, allocation
> serialization), Bruno ×4 requests. Verified: Order suite **76 passed**; Pint clean;
> phpstan **[OK]** — baseline extended by exactly the new kernel files' Eloquent
> false-positive family (106 occurrences; also noted: the pre-existing 188-error
> drift visible on Nov 3 is gone — fixed upstream by the intervening transfer
> commits). Deviations worth review: `sku`/`tax_class_name` snapshot null for now
> (needs a Catalog/Tax cross-module read — Phase 2 decision); subtotal semantics
> pinned by test (tax-exclusive model: subtotal = Σ line goods, tax stacks on top);
> `Order::$fillable` gained the tax snapshot columns (they were silently dropped by
> mass-assignment — latent bug caught by the new tests).

Lifecycle states settled up front (review §5): invoice/CN legal status
`active|void`; PDF artifact status per D7; transitions — issue (number+snapshot,
one txn) → generate (retryable, same number) → ready | generation_failed; void
(any time, keeps number, CNs stay attached); reissue (next revision, fresh number).
Nothing below may invent a transition not listed here.

- [ ] Migrations: the four tables above (additive, per marketplace-policy §5.2).
- [ ] Models + schemas (`OrderInvoice`, `OrderInvoiceItem`, `OrderCreditNote`, enums)
      with relations; `credited_amount` as an accessor.
- [ ] `DocumentNumberService`: next number per (type, year) — first-row-safe
      initialization (D3), store-TZ year; called inside the caller's transaction.
- [ ] `IssueInvoiceAction(Order $order)`: guards (no ACTIVE invoice; payment status in
      {**PAID, PARTIALLY_REFUNDED, REFUNDED**} — v2.2: partial refunds must pass the
      lazy-issuance path, final review §1.2), D4 snapshots (items from `OrderItem`
      snapshots — tax_amount and line_total **copied, never recalculated**; sku/variant
      from the variant relation ⚠ verify what's nullable for deleted variants; ⚠ verify
      whether items persist a per-line coupon allocation), D2 revision + single-ACTIVE
      enforcement under the parent-order lock, persists. No PDF.
- [ ] `credited_amount`/`balance`: the **document service owns the calculation** (one
      site for the Σ + minor-unit conventions); the model accessor — if kept —
      **delegates to it**; the resource exposes the service's result. No duplicated
      aggregation logic in model + resource + service (final review §1.5).
- [ ] `GenerateDocumentPdfAction`: D7 lifecycle — mark generating → render via
      D12 interface → store to disk + checksum → ready; failure → generation_failed.
      Separate from issuance.
- [ ] `VoidInvoiceAction`: guarded, keeps number, allows reissue; audit log.
- [ ] Pest: **critical set from the reviews** — two simultaneous issuance requests;
      concurrent first-time sequence initialization; void + replacement reissue;
      snapshot correctness (tax copied, not recalculated); double-issue guard;
      numbering year boundary in store TZ; **partial-refund lazy issuance** (payment
      status PARTIALLY_REFUNDED passes the guard — final review §1.2).
- [ ] Admin endpoints (list w/ filters, show, issue, void, **retry of failed
      generation** — per D7 v2.2 the retry-failure path is admin-accessible; full
      regeneration is ops-only artisan, no endpoint) + permission tests; Bruno requests.

### Phase 2 — Rendering (mpdf behind the D12 interface)

- [x] `composer require mpdf/mpdf`; `MpdfRenderer` implementing the interface; PDF
      service (Blade → HTML → mpdf → artifact with checksum + template_version).
      *(done 2026-11-10 — `DocumentRendererInterface` + `DocumentView`/`PdfArtifact` in
      Core Gateways/Order (the D12 seam, model-free so country extensions never touch
      Order internals); `MpdfRenderer` + `DocumentPdfService` in
      `Modules/Order/app/Documents` — the service owns the D7 lease CAS, storage,
      sha256 checksum and failure state; `order:sweep-generating-documents` releases
      expired leases, scheduled every 15 min)*
- [x] **PoC gate before building out:** render a real fa invoice and an ar invoice —
      shaping, mixed-direction, numerals, currency, multi-page. Failure → revisit D6
      before proceeding (fallback dompdf is a *decision*, not a default). *(structural
      gate PASSED 2026-11-10 — valid PDF, 3-page paginated RTL table, fa+ar+mixed
      direction rendered clean; artifact at `apps/backend/storage/poc-rtl.pdf` for the
      human eyeball gate, which the plan §7 correctly leaves to the owner)*
- [x] Templates **en** (LTR) and **fa** (RTL proof) first — then de/fr/es/ar; templates
      read only the document snapshot (never live order data). *(one RTL-aware layout
      `resources/views/documents/document.blade.php` + per-locale label files
      `lang/{en,fa,de,fr,es,ar}/documents.php` — agent-drafted strings flagged for
      owner review per the launch roadmap's open assumption; templates read the
      DocumentView snapshot exclusively)*
- [x] Seller identity block from new settings (store legal name/address/tax id —
      Setting module pattern; admin Settings UI entry). *(backend complete 2026-11-10:
      4 settings enum cases + name-enum migration + `AppliesStoreIdentity` boot
      applier (cached, flushed by UpdateSettingAction — the timezone pattern);
      `IssueInvoiceAction` freezes `config('store.*')` into the snapshot with
      app-name fallback. **Remaining sliver:** the admin Settings UI fields (React) —
      backend accepts the settings already)*
- [x] Wire generation into issue/void-reissue/CN creation paths (post-commit).
      *(issue + void-reissue wired 2026-11-10 — synchronous post-commit, D7-faithful;
      CN creation is Phase 4 and wires there)*
- [x] Tests: issued invoice reaches `ready` with stored bytes + checksum; generation
      failure → `generation_failed` → successful retry reuses the same number and
      snapshot; S3-down → failure state, later retry succeeds; en + fa render without
      exceptions; multi-page with many items (pagination + totals). *(all but the
      S3-down case done 2026-11-10, via the renderer-seam failure injection — the
      storage-failure path shares the same try/catch state machine; download guards
      (401 / ready-only 409 / retry-only-failed 409) also covered. Admin endpoints:
      `GET invoices/{id}/pdf`, `POST invoices/{id}/pdf/retry` + Bruno ×2)*

### Phase 3 — Automation & delivery (the revised event work)

- [x] **New `RefundFinalizedEvent`** (D10) in Core contracts with the payload above;
      fired from the refund finalize path (Payment) **after the finalize transaction
      commits** (D15 v2.2: at-least-once intent, documented crash window). OrderRefundedEvent untouched.
      *(done 2026-11-10 — contract + `FiresRefundFinalizedEvent` shared dispatcher
      (`DB::afterCommit`) wired at all three finalize sites: direct admin refund,
      webhook confirm, ambiguity resolution-to-success. One deviation from the
      payload sketch: `order_code` is omitted — the Phase 4 listener resolves it
      from `order_id`, one indexed lookup, avoiding a cross-module fetch at every
      dispatch site. Amount rides as `amount_minor` (D11). Tests: dispatched exactly
      once per finalize with uuid/amount/currency/reason; not dispatched for
      resolution-to-failed)*
- [ ] **`documents:reconcile-refunds`** (D15 v2.2): finds finalized refunds whose
      `refund_uuid` has no credit note → runs the same idempotent CN path; scheduled
      daily; closes the crash-between-commit-and-dispatch window without an outbox.
      *(**moved to Phase 4 by execution necessity** — the reconcile command's "run
      the same idempotent CN path" cannot exist before the CN listener it mirrors;
      building it against nothing would be a stub. It lands with Phase 4's credit
      notes, its natural home)*
- [x] `IssueInvoiceOnOrderPaid` listener on `OrderPaidEvent` (mirror Notification's
      `EventServiceProvider`), gated by `invoices.auto_issue`; idempotent via the
      single-ACTIVE assertion. **Free orders:** verify the zero-total checkout path ⚠
      (does it produce a payment success, or skip payment?) and hook the order
      completion/paid transition accordingly so a fully-discounted order still gets
      its invoice — not a manual-only exception (review §4). *(done 2026-11-10 —
      gate is `order.invoices.auto_issue` config (env-overridable), default on; the
      listener failure-isolates via OrderException swallow + report. Zero-total
      verified: no payment-skipping path exists — CheckoutAction creates UNPAID
      orders and every paid transition funnels through `OrderGateway::markAsPaid`,
      the single OrderPaidEvent dispatch site, so the listener covers free orders,
      webhook settles and manual confirms identically)*
- [x] Verify the manual-confirm path (`UpdatePaymentAction` → paid) fires the same
      event ⚠ — single source of "paid" or the listener subscribes to both dispatchers.
      *(verified 2026-11-10 — `UpdatePaymentAction:43` and `HandleWebhookAction:90`
      both call the same `markAsPaid()`; no second dispatcher exists)*
- [x] Notification: "Your invoice is ready" email (per-locale templates, 6 locales)
      — **queued async** once `pdf_status` → ready, attaching the STORED pdf;
      delivery retries independently of generation, and a notification failure
      never re-issues or re-numbers a document (review §3.2). *(done 2026-11-10 —
      `InvoiceReadyEvent` carries the artifact's storage coordinates so the queued
      listener never imports Order models (cross-module discipline); per-locale
      blade subject/body ×6 + database-channel lang entries ×6 (the engine's
      locale-keyed nested arrays — placement mattered); the email channel gained
      generic attachment threading (`NotificationMessage.attachments` →
      `EmailMessage` → `DynamicNotification::attachedDocuments` — named to NOT
      collide with Mailable's own `$attachments`, whose shadowing killed test
      processes with silent exit-2s, a memorable debugging hour); database channel
      still gets its inbox row; `order.invoice.ready` enters the preference matrix
      as user-configurable [database, email])*
- [ ] Storefront: profile → orders → invoice download (stream stored bytes; only
      `ready`; permission = order ownership — **test that another customer's invoice
      403s**, review §7). *(backend done 2026-11-10: `GET /user/orders/{code}/invoices`
      + `GET /user/invoices/{id}/pdf` — ownership enforced as a 404 (ids stay
      non-enumerable; the plan's 403-ask was hardened to 404-on-foreign, matching
      the codebase's user-surface convention), ready-only 409, guest 401, idempotent
      replay under paid-event redelivery, auto-issue-off leaves manual admin issue
      working — 7-test `InvoiceAutomationTest`. **The React/Next UI surfaces remain
      open.**)* Admin UI: invoices tab under Orders (list/filter/download/
      void/reissue/retry-failed-generation) + order detail link. The invoices tab
      **displays the document chain explicitly** — original invoice, its credit notes,
      and any replacement (revision numbers) side by side — so operators cannot
      misread the accounting history of a void+reissue (final review §2).
- [ ] i18n keys ×6 (admin + storefront + backend notification lang files); parity check.

### Phase 4 — Credit notes (policy-per-D5, locking-per-D14)

- [x] `IssueCreditNoteOnRefundFinalized` listener on `RefundFinalizedEvent`, with an
      explicit sequence (review §2.2): **find-or-issue the invoice** (its own
      idempotent D2 transaction — lazy-issue per the "refund before invoice" policy)
      → **validate the invoice is ACTIVE** (never a CN against a voided invoice)
      → **CN transaction** (D14: lock invoice row, Σ active CNs in minor units
      inside the lock, validate, allocate CN number, insert) → **generate PDF
      artifacts** (D7 lifecycle) → **notify when ready**. A failed PDF never
      prevents the credit note from being recorded — the CN row exists with
      `pdf_status` pending/failed and retries independently. Idempotent via unique
      `refund_uuid` (duplicate event → no-op). *(done 2026-11-10 — one execution
      refinement: the voided-invoice case routes through the find-or-issue's
      lazy re-issue, so the CN targets the fresh ACTIVE revision rather than
      throwing — tested as such; a hard-refuse remained only for the
      no-active-invoice-possible case)*
- [x] Refund exceeding remaining balance: rejected by the same lock-guarded check
      (the refund ledger already prevents this upstream — the CN guard is the
      second line of defense, tested). *(done 2026-11-10 — `credit_note.exceeds_balance`
      ×6 locales)*
- [x] Invoice resource gains `credited_amount` + `balance` (derived, D4/D11).
      *(shipped with the Phase 1 kernel — `DocumentBalanceService` is the single
      calculation site)*
- [x] Admin: CN list/filter/download; storefront: download from order + returns pages.
      *(admin done 2026-11-10: `GET /admin/credit-notes` + `/pdf`, Bruno ×2;
      storefront download rides the existing invoice endpoints — the CN download
      from the profile orders page lands with the Phase 3 storefront UI sliver)*
- [ ] "Credit note issued" email to the customer (attachment, per-locale).
      *(deliberately deferred with the storefront UI sliver — the invoice-ready
      email machinery covers the pattern; the CN email is one more matrix entry
      + blade pair once the UI surface lands)*
- [x] Pest: full lifecycle (pay → invoice → partial refund → CN → balances → second
      refund → CN → fully credited); **concurrent CNs cannot exceed invoice total**;
      duplicate refund event → no duplicate CN; voided-CN balance semantics; CN
      generation failure → retry same number. *(done 2026-11-10 as
      `CreditNoteLifecycleTest` ×7: event → CN against invoice with ready PDF +
      derived balances; triple-dispatch → one CN; pre-invoice refund → lazy invoice
      + CN; over-balance refused (second CN rejected, first stands); voided → CN
      routes to replacement revision 2; reconcile cuts a missed CN idempotently;
      admin list/download + guest 401. Plus the reconcile command, daily-scheduled,
      closing the D15 window)*

### Phase 5 — Polish & operations

- [x] `apps/backend/docs/flows/invoice-flow.md` — lifecycle states/transitions,
      numbering policy (D3), refund↔CN policy (D5), **ops recovery runbook**:
      generation failure triage, checksum-verified regeneration, S3 outage handling.
      *(done 2026-11-10 — three-layers intro, both lifecycles as diagrams-in-words,
      surfaces table, policies, four-row ops runbook incl. corrupted-artifact
      recovery, the renderer seam)*
- [ ] Regenerate per-module ERDs (`php artisan erd:generate`) for the new Order
      tables; module graph if policy requires (rule since `e70c2cf`).
      *(deferred to the release-time batch — ERD regen touches every module and
      belongs with the module-graph pass at the next version release)*
- [ ] Note for later: invoices belong in the Order module's per-module data-transfer
      export (standard `8502607`) — small follow-up, not a blocker.
- [x] CHANGELOG entry; README feature table row once demoable. *(CHANGELOG entry
      done 2026-11-10 under [Unreleased]/Added; the README row lands with the
      admin/storefront UI when it's demoable)*
- [x] Full suite + phpstan neutral (extend baseline only for new-test-file false
      positives, A.1/A.3 pattern) + Pint. *(2026-11-10 — baseline extended only
      with the new files' Eloquent false-positive family; Pint clean)*

**Remaining slivers after this pass (tracked here so silence doesn't read
as oversight):** ~~the admin invoices tab + storefront download button~~, ~~the
CN-issued email~~, ~~the store-identity fields in the admin Settings UI~~,
~~the S3-down test case~~, and the ERD/module-graph regen at release time.

*Slivers closed 2026-11-12/13 — this paragraph had gone stale:* the CN email,
S3-down test, admin invoices tab and storefront download button landed in
`6b93b118`/`64f36d6d`/`38259bae` (2026-11-12) without re-ticking here. A
code-verified sweep on 2026-11-13 then closed what was genuinely still open:
the **admin credit-notes table** (the API layer, zod type and query hook had
shipped unused — a Credit notes tab now sits on the invoices page, sharing its
URL state so a number search works from either side of the document chain) and
the **store-identity settings defect** (the four fields rendered inside the
announcement tab's conditional and their DB rows were never created, so the
always-submitting settings form hit `firstOrFail` — rows now seed idempotently
with the FX pattern and the fields live in their own "Store identity" tab;
`StoreIdentitySettingsTest` pins seeding, PATCH persistence and re-seed
preservation). Still deferred to release time: the ERD/module-graph regen.
Known future work, out of sliver scope: user-facing credit-note endpoints +
storefront CN downloads (CN list/PDF is admin-only today).

## 6. Edge cases (each maps to a named decision + a test)

| Scenario | Behavior |
|---|---|
| Two simultaneous issuance requests | Single-ACTIVE assertion under row locks → one invoice (D2) |
| Concurrent first-time sequence init | Upsert-or-lock on (type, year) (D3) |
| Void + reissue | Revision N+1, fresh number, CNs stay on the original (D2) |
| Refund event identity / duplicates | `RefundFinalizedEvent` per refund; unique `refund_uuid` (D10, D15) |
| Concurrent credit notes over balance | Invoice-row lock + in-lock sum (D14) |
| Failed PDF generation | `generation_failed`; retry reuses number + snapshot (D7) |
| S3 temporarily unavailable | Same as generation failure; storage retry later (D7) |
| Zero-total order (fully discounted) | Invoice issued via the paid/completed transition — verified path in Phase 3 |
| Refund before invoice (auto-issue off) | Lazy-issue invoice, then CN (Phase 4) |
| Store changes legal identity | Existing documents keep the frozen snapshot (D4) |
| Store changes invoice prefix | Old documents keep their stored number; only future numbers change (D3) |
| New calendar year | New sequence row per store-TZ year (D3) |
| Invoice with many items | Multi-page template + totals (Phase 2 test) |
| Multiple currencies | Charged currency + its precision; FX snapshot = reference line only |
| Invoice voided after partial refund | CNs stay attached to the voided invoice; balance carry-over is an explicit admin workflow (D2) |
| Refund exceeds remaining balance | Refund ledger prevents upstream; CN guard rejects as second line (D14) |

## 7. Test plan (review's table folded into the phases)

**Critical:** simultaneous issuance · concurrent sequence init · void + replacement ·
refund event carries refund identity · duplicate refund event → one CN · concurrent
CNs ≤ total · cross-customer download 403.
**High:** PDF failure + retry same number · zero-total issuance · store-TZ year
boundary · seller identity frozen after settings change · fa/ar mixed RTL + multi-page
· event dispatch after commit · listener retry safety.
**Human gate:** download en + fa invoices and one CN from the dev compose demo and
eyeball them — tests assert structure, not beauty.

## 8. Out of scope / non-goals

- **Manual credit notes** — planned follow-up iteration (D5); v1 automates the
  refund-backed policy only.
- **CN line-item breakdowns** — v1 CNs are amount + reason + references; per-line tax
  allocation arrives when a jurisdiction needs it (extension territory).
- Packing slips (arrive with the shipping/carrier work, gap C).
- Country document providers (Factur-X, XRechnung, Moadian) — extensions implementing
  the D12 interface; core ships the generic provider only.
- **Jurisdiction-neutral credit notes (documented limitation, review §2.5):** generic
  amount+reason CNs are not guaranteed-compliant tax documents in every jurisdiction;
  the PDFs make no compliance claims; per-line tax adjustments and regional formats
  arrive via extensions (D12).
- **Transactional outbox** — deferred until a second event consumer exists; v1 closes
  the crash window with reconciliation instead (D15 v2.2).
- Per-currency invoicing, invoice editing (void + reissue only), customer self-issue,
  payment terms/due dates (Baxela charges upfront — invoices are always paid).

## 9. Suggested commits (one per phase)

1. `feat(backend/order): invoice and credit note kernel with revisioned lifecycle`
2. `feat(backend/order): render document pdfs via mpdf with rtl support`
3. `feat(backend/payment): refund finalized event and invoice automation`
4. `feat(backend/order): credit notes on finalized refunds`
5. `docs(backend/order): document the invoice and credit note flow`

## 10. Review trail

- v1 (2026-11-03) → external review 2026-11-07 (8.5/10): seven priority fixes — event
  contract, void/reissue contradiction, numbering wording, line-item snapshots,
  refund↔CN distinction, PDF lifecycle, zero-total + CN concurrency.
- v2 incorporates all seven; goes beyond the review on one point (D10): the review
  proposed extending `OrderRefundedEvent`, but code verification showed that event
  fires only on the full-refund crossing and carries no refund identity — a per-refund
  event is the correct fix.
- v2.1 (2026-11-07): second review — 9.3/10, **approved** with corrections, folded in
  directly per its own recommendation. P0: parent-Order-row lock for issuance (D2);
  explicit voided-invoice/CN business rule, no balance transfers in V1 (D2); PDF
  failure never blocks CN issuance (Phase 4 sequence). P1: promotion/tax-class
  snapshot fields + line semantics (§4); store-timezone resolver (D3); async
  independent email (Phase 3). P2: PdfArtifact DTO + renderer/storage separation
  (D12); jurisdiction-neutral CN caveat (§8). Added D16 (record ≠ representation ≠
  storage) from the review's §4.
- v2.2 (2026-11-07): final review — 9.4/10, **approved**, four clarifications folded in
  as an addendum per its own recommendation. P0: event guarantee reworded to
  at-least-once + `documents:reconcile-refunds` reconciliation (outbox deferred, D15);
  `PARTIALLY_REFUNDED` passes lazy issuance (guard enum set, Phase 1). P1: invoice tax
  and line totals **copied from order snapshots, never recalculated**; coupon
  allocation semantics pinned (totals block unless per-line data exists — §4);
  retry-failed-generation vs ops-only regeneration split + generation lease for stuck
  `generating` rows (D7); `credited_amount` accessor delegates to the document service
  (Phase 1); admin UI displays the full document chain (Phase 3). Estimate
  review-calibrated to 1–2 weeks for the complete plan.

## 11. Related docs

- `docs/PLATFORM_GAPS_ROADMAP.md` §Phase B (B.1) and §6 policy "documents are snapshots".
- `apps/backend/docs/flows/order-state-machine.md` — where "paid" sits in the lifecycle.
- `docs/EXTENSION_MARKETPLACE_ROADMAP.md` §5.2 — additive-migrations policy binding the
  schema above once third-party extensions exist.
