<?php

namespace Modules\Order\Actions\Admin\Invoice;

use Illuminate\Support\Facades\DB;
use Modules\Order\Exceptions\OrderException;
use Modules\Order\Models\Order;
use Modules\Order\Models\OrderInvoice;
use Modules\Order\Schemas\OrderInvoice\OrderInvoiceSchema;
use Modules\Order\Schemas\OrderInvoice\OrderInvoiceStatusEnum;

class VoidInvoiceAction
{
    /**
     * Void an issued invoice (D8): the row and its number are retained —
     * numbers are never reused — and the order may be re-invoiced under
     * the next revision via IssueInvoiceAction. Guarded by the same
     * parent-order lock as issuance (D2).
     *
     * @throws OrderException when the invoice is missing or already void
     */
    public function handle(int $invoiceId, string $reason): OrderInvoice
    {
        return DB::transaction(function () use ($invoiceId, $reason): OrderInvoice {
            $invoice = OrderInvoice::query()->find($invoiceId);

            if (is_null($invoice)) {
                throw OrderException::invoiceNotFound();
            }

            Order::query()->lockForUpdate()->findOrFail($invoice->{OrderInvoiceSchema::ORDER_ID});

            if ($invoice->{OrderInvoiceSchema::STATUS} !== OrderInvoiceStatusEnum::ACTIVE) {
                throw OrderException::invoiceInvalidTransition();
            }

            $invoice->update([
                OrderInvoiceSchema::STATUS => OrderInvoiceStatusEnum::VOID,
                OrderInvoiceSchema::VOIDED_AT => now(),
                OrderInvoiceSchema::VOID_REASON => $reason,
            ]);

            return $invoice;
        });
    }
}
