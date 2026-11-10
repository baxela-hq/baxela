<?php

namespace Modules\Order\Actions\Admin\CreditNote;

use Illuminate\Support\Facades\DB;
use Modules\Core\Support\Money;
use Modules\Order\Actions\Admin\Invoice\IssueInvoiceAction;
use Modules\Order\Documents\DocumentPdfService;
use Modules\Order\Exceptions\OrderException;
use Modules\Order\Models\OrderCreditNote;
use Modules\Order\Models\OrderInvoice;
use Modules\Order\Schemas\Document\DocumentSequenceTypeEnum;
use Modules\Order\Schemas\OrderCreditNote\OrderCreditNoteSchema;
use Modules\Order\Schemas\OrderCreditNote\OrderCreditNoteStatusEnum;
use Modules\Order\Schemas\OrderInvoice\OrderInvoiceSchema;
use Modules\Order\Schemas\OrderInvoice\OrderInvoiceStatusEnum;
use Modules\Order\Support\DocumentBalanceService;
use Modules\Order\Support\DocumentNumberService;
use Throwable;

/**
 * Cut a refund-backed credit note against an order's invoice (gap-B1
 * Phase 4, D5/D14). The explicit sequence from the plan §Phase 4:
 *
 *  1. find-or-issue the invoice (its own idempotent D2 transaction —
 *     lazy-issuance when the refund preceded invoicing);
 *  2. validate the invoice is ACTIVE (never a CN against a voided one);
 *  3. the CN transaction itself: lock the invoice row, compute Σ active
 *     CNs in minor units INSIDE that lock, validate the new amount fits
 *     the remaining balance, allocate the CN number, insert;
 *  4. generate the PDF artifact (D7) — a failure here NEVER prevents
 *     the CN from being recorded; the row lands generation_failed and
 *     retries independently.
 *
 * Idempotent per refund: unique refund_uuid means a duplicate event
 * finds the existing CN and returns it unchanged.
 */
class IssueCreditNoteAction
{
    public function __construct(
        protected DocumentBalanceService $balances,
        protected DocumentNumberService $numbers,
    ) {}

    /**
     * @param  string  $refundUuid  the finalized refund's uuid — the idempotency key (nullable for future manual CNs)
     * @param  int  $amountMinor  the refund amount in integer minor units
     */
    public function handle(int $orderId, string $refundUuid, int $amountMinor, ?string $reason = null): OrderCreditNote
    {
        // 1. Idempotency: a duplicate event returns the existing CN.
        $existing = OrderCreditNote::query()
            ->where(OrderCreditNoteSchema::REFUND_UUID, $refundUuid)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        // 2. Find-or-issue the invoice — the refund's accounting
        //    document hangs off a legal invoice, lazily issued if the
        //    refund landed before invoicing (the "refund before
        //    invoice" policy from Phase 1).
        $invoice = app(IssueInvoiceAction::class)->handle($orderId);

        // 3. The CN transaction, invoice-row-locked (D14).
        $creditNote = DB::transaction(function () use ($invoice, $refundUuid, $amountMinor, $reason): OrderCreditNote {
            $locked = OrderInvoice::query()
                ->lockForUpdate()
                ->findOrFail($invoice->id);

            if ($locked->{OrderInvoiceSchema::STATUS} !== OrderInvoiceStatusEnum::ACTIVE) {
                // A voided invoice is never the target of a refund-backed
                // CN (D2 guard) — relating it is a manual workflow.
                throw OrderException::invoiceInvalidTransition();
            }

            $creditedMinor = $this->balances->creditedMinor($locked);
            $totalMinor = Money::fromDecimal((string) $locked->{OrderInvoiceSchema::TOTAL_AMOUNT});

            if ($amountMinor <= 0 || $creditedMinor + $amountMinor > $totalMinor) {
                // The refund ledger prevents this upstream; this guard is
                // the second line of defense (D14) and fails loudly.
                throw OrderException::creditNoteExceedsBalance();
            }

            return OrderCreditNote::query()->create([
                OrderCreditNoteSchema::INVOICE_ID => $locked->id,
                OrderCreditNoteSchema::REFUND_UUID => $refundUuid,
                OrderCreditNoteSchema::NUMBER => $this->numbers->next(DocumentSequenceTypeEnum::CREDIT_NOTE),
                OrderCreditNoteSchema::STATUS => OrderCreditNoteStatusEnum::ACTIVE,
                OrderCreditNoteSchema::ISSUED_AT => now(),
                OrderCreditNoteSchema::CURRENCY_CODE => $locked->{OrderInvoiceSchema::CURRENCY_CODE},
                OrderCreditNoteSchema::AMOUNT => Money::toDecimal($amountMinor),
                OrderCreditNoteSchema::REASON => $reason,
            ]);
        });

        // 4. The artifact — failure-isolated from the recording.
        try {
            app(DocumentPdfService::class)->generate($creditNote);
        } catch (Throwable $exception) {
            report($exception);
        }

        return $creditNote->refresh();
    }
}
