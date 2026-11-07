<?php

namespace Modules\Order\Support;

use Modules\Core\Support\Money;
use Modules\Order\Models\OrderInvoice;
use Modules\Order\Schemas\OrderCreditNote\OrderCreditNoteSchema;
use Modules\Order\Schemas\OrderCreditNote\OrderCreditNoteStatusEnum;
use Modules\Order\Schemas\OrderInvoice\OrderInvoiceSchema;

/**
 * The ONE calculation site for invoice credited amounts and balances
 * (final review §1.5): integer minor units in, decimal out. Model
 * accessors and resources delegate here — no duplicated aggregation.
 */
class DocumentBalanceService
{
    /**
     * Σ active credit notes against the invoice, in minor units.
     */
    public function creditedMinor(OrderInvoice $invoice): int
    {
        return Money::fromDecimal((string) $invoice->creditNotes()
            ->where(OrderCreditNoteSchema::STATUS, OrderCreditNoteStatusEnum::ACTIVE)
            ->sum(OrderCreditNoteSchema::AMOUNT));
    }

    /**
     * @return array{credited: string, balance: string}
     */
    public function amounts(OrderInvoice $invoice): array
    {
        $credited = $this->creditedMinor($invoice);
        $total = Money::fromDecimal((string) $invoice->{OrderInvoiceSchema::TOTAL_AMOUNT});

        return [
            'credited' => Money::toDecimal($credited),
            'balance' => Money::toDecimal(max(0, $total - $credited)),
        ];
    }
}
