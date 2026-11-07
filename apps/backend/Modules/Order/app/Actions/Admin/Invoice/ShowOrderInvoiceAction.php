<?php

namespace Modules\Order\Actions\Admin\Invoice;

use Modules\Order\Models\OrderInvoice;
use Modules\Order\Schemas\OrderInvoice\OrderInvoiceSchema;

class ShowOrderInvoiceAction
{
    public function handle(int $invoiceId): OrderInvoice
    {
        return OrderInvoice::query()
            ->with(OrderInvoiceSchema::RES_ITEMS)
            ->findOrFail($invoiceId);
    }
}
