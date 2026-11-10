<?php

namespace Modules\Order\Actions\User\Invoice;

use Modules\Core\Utils\Auth;
use Modules\Order\Exceptions\OrderException;
use Modules\Order\Models\Order;
use Modules\Order\Models\OrderInvoice;
use Modules\Order\Schemas\Document\DocumentPdfStatusEnum;
use Modules\Order\Schemas\Order\OrderSchema;

/**
 * The customer's invoices for one of THEIR orders (gap-B1 Phase 3):
 * ownership is the whole authorization surface — the order must belong
 * to the caller, and then every invoice of that order is theirs.
 *
 * @return list<array<string, mixed>>
 */
class ListOrderInvoicesUserAction
{
    public function __construct(protected Order $model) {}

    public function handle(string $orderCode): array
    {
        $order = $this->model->query()
            ->where(OrderSchema::ORDER_CODE, $orderCode)
            ->first();

        if (is_null($order) || (int) $order->{OrderSchema::USER_ID} !== (int) Auth::id()) {
            throw OrderException::orderNotFound();
        }

        return $order->invoices()
            ->orderByDesc(OrderSchema::CREATED_AT)
            ->get()
            ->map(fn (OrderInvoice $invoice): array => [
                'id' => (int) $invoice->id,
                'number' => (string) $invoice->number,
                'revision' => (int) $invoice->revision,
                'status' => $invoice->status->value,
                'issued_at' => $invoice->issued_at?->toIso8601String(),
                'pdf_ready' => $invoice->pdf_status === DocumentPdfStatusEnum::READY,
            ])
            ->all();
    }
}
