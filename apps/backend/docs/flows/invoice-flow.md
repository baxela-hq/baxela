# Invoice & Credit Note Flow (gap-B1)

Legal documents for orders: **invoices** (the commercial record of a
sale) and **credit notes** (the accounting adjustment for a refund).
Both are immutable snapshots with sequential numbers, both carry a
regenerable PDF artifact, and neither is ever edited — mistakes are
corrected by voiding (and reissuing the invoice).

## The three layers (D16)

1. **The financial record** — the DB row: number, frozen snapshot, legal
   status (`active` | `void`). Never hard-deleted.
2. **The representation** — the PDF artifact on disk, produced through
   an explicit lifecycle: `pending_generation → generating → ready |
   generation_failed`, with a lease (`generation_claimed_at`) so a
   crashed worker can't wedge a document; `order:sweep-generating-documents`
   (every 15 min) releases expired leases.
3. **The storage location** — `documents/{number}.pdf` on the
   configured disk; recovery regenerates representations and never
   touches snapshots.

## Numbering (D3)

`{INV|CN}-{year}-{6-digit seq}` per type per calendar year in the
store's timezone, allocated inside the issuing transaction via the
dedicated sequence table. Numbers are never reused; voided documents
keep theirs. Changing the prefix affects only future numbers.

## Lifecycle

```
Order paid (any path — webhook settle, manual confirm, free order)
  └─ OrderPaidEvent → IssueInvoiceOnOrderPaid (gated by
     order.invoices.auto_issue, default on; idempotent via the
     single-ACTIVE assertion under the parent-order lock)
       └─ IssueInvoiceAction: allocate number + freeze snapshot
          (one transaction; NO PDF inside it)
            └─ DocumentPdfService.generate (post-commit, lease CAS
               → render via DocumentRendererInterface → store →
               sha256 checksum → ready)
                 └─ InvoiceReadyEvent → "Your invoice is ready" email
                    with the stored PDF attached (queued; failures
                    never re-issue or renumber)

Refund finalized (direct admin refund / webhook confirm / resolve-to-success)
  └─ RefundFinalizedEvent (after the finalize commit; at-least-once)
       └─ IssueCreditNoteOnRefundFinalized
            └─ find-or-issue the invoice (lazy re-issue if the
               original is voided — a CN never targets a voided
               invoice) → lock the invoice row → Σ active CNs inside
               the lock → validate against remaining balance →
               allocate CN number → insert → generate PDF (failure
               never blocks the recording)
```

The **crash window** between a refund's commit and its event dispatch
is closed by `order:reconcile-refund-credit-notes` (daily): finalized
refunds whose `refund_uuid` has no credit note get one through the same
idempotent path.

## Surfaces

| Who | What |
|---|---|
| Admin | `GET /order/admin/invoices` (list+filters, balances derived), `POST /orders/{id}/invoice`, `PATCH /invoices/{id}/void`, `GET /invoices/{id}/pdf` (ready-only), `POST /invoices/{id}/pdf/retry` (failed-only), `GET /credit-notes`, `GET /credit-notes/{id}/pdf` |
| Customer | `GET /order/user/orders/{code}/invoices` (own orders only), `GET /user/invoices/{id}/pdf` (ownership = order; foreign ids 404) |
| Email | Invoice-ready notification with the stored PDF attached, per-locale ×6, preference-matrix gated |

## Policies

- **Immutability**: snapshots are copied once (D4); documents never
  join live order rows or current settings for money.
- **Money**: all arithmetic in integer minor units (D11); decimals only
  at the storage boundary and rendering.
- **Credit notes**: one per finalized refund (D5, the automated
  policy); manual CNs are a planned follow-up. CN amounts are validated
  against the invoice's remaining balance under a row lock (D14) — the
  refund ledger is the first line of defense, this is the second.
- **Yank analog — void**: keeps the number and history; a reissue takes
  the next revision with a fresh number (D2); refund-backed CNs route
  to the active revision, never the voided one.
- **Zero-total orders**: every paid transition funnels through
  `OrderGateway::markAsPaid()` — the single `OrderPaidEvent` dispatch
  site — so free orders invoice identically to paid ones.

## Ops runbook

| Symptom | Action |
|---|---|
| `pdf_status = generation_failed` | Admin: `POST invoices/{id}/pdf/retry` (same number + snapshot); check `storage/logs` for the reported render/storage error |
| `pdf_status = generating` stuck | The 15-min sweep releases expired leases (30 min default); `order:sweep-generating-documents --lease-minutes=N` to force |
| Refund without a credit note | `order:reconcile-refund-credit-notes` (idempotent); if it reports failures, the reported exception names the policy conflict |
| Corrupted ready artifact | Ops-only: delete the stored file, reset `pdf_status` to `generation_failed` via tinker, then admin retry — regeneration is checksum-verified and reuses the number |

## Renderer seam (D12)

`DocumentRendererInterface` (Core Gateways/Order) renders a model-free
`DocumentView` into a `PdfArtifact`. One implementation today
(`MpdfRenderer`, RTL-capable, shared-hosting-safe). Country-specific
document providers (Factur-X, XRechnung, Moadian) arrive as extensions
implementing the same interface — templates and engines stay behind it.
