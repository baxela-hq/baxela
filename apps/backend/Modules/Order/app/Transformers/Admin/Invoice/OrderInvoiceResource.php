<?php

namespace Modules\Order\Transformers\Admin\Invoice;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Core\Support\Money;
use Modules\Order\Models\OrderInvoice;
use Modules\Order\Schemas\OrderInvoice\OrderInvoiceSchema;
use Modules\Order\Support\DocumentBalanceService;

/**
 * @mixin OrderInvoice
 */
class OrderInvoiceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        $amounts = app(DocumentBalanceService::class)->amounts($this->resource);

        // Money fields render as formatted decimals — documents read like
        // documents, and the assertion surface is engine-independent.
        $money = fn (mixed $value): ?string => $value === null
            ? null
            : Money::toDecimal(Money::fromDecimal((string) $value));

        return [
            OrderInvoiceSchema::ID => $this->resource->{OrderInvoiceSchema::ID},
            OrderInvoiceSchema::ORDER_ID => $this->resource->{OrderInvoiceSchema::ORDER_ID},
            OrderInvoiceSchema::REVISION => $this->resource->{OrderInvoiceSchema::REVISION},
            OrderInvoiceSchema::NUMBER => $this->resource->{OrderInvoiceSchema::NUMBER},
            OrderInvoiceSchema::STATUS => $this->resource->{OrderInvoiceSchema::STATUS},
            OrderInvoiceSchema::ISSUED_AT => $this->resource->{OrderInvoiceSchema::ISSUED_AT},
            OrderInvoiceSchema::VOIDED_AT => $this->resource->{OrderInvoiceSchema::VOIDED_AT},
            OrderInvoiceSchema::VOID_REASON => $this->resource->{OrderInvoiceSchema::VOID_REASON},
            OrderInvoiceSchema::CURRENCY_CODE => $this->resource->{OrderInvoiceSchema::CURRENCY_CODE},
            OrderInvoiceSchema::SUBTOTAL_AMOUNT => $money($this->resource->{OrderInvoiceSchema::SUBTOTAL_AMOUNT}),
            OrderInvoiceSchema::DISCOUNT_AMOUNT => $money($this->resource->{OrderInvoiceSchema::DISCOUNT_AMOUNT}),
            OrderInvoiceSchema::COUPON_CODE => $this->resource->{OrderInvoiceSchema::COUPON_CODE},
            OrderInvoiceSchema::SHIPPING_AMOUNT => $money($this->resource->{OrderInvoiceSchema::SHIPPING_AMOUNT}),
            OrderInvoiceSchema::SHIPPING_TAX_AMOUNT => $money($this->resource->{OrderInvoiceSchema::SHIPPING_TAX_AMOUNT}),
            OrderInvoiceSchema::TAX_AMOUNT => $money($this->resource->{OrderInvoiceSchema::TAX_AMOUNT}),
            OrderInvoiceSchema::TOTAL_AMOUNT => $money($this->resource->{OrderInvoiceSchema::TOTAL_AMOUNT}),
            'credited_amount' => $amounts['credited'],
            'balance' => $amounts['balance'],
            OrderInvoiceSchema::BILLING_ADDRESS => $this->resource->{OrderInvoiceSchema::BILLING_ADDRESS},
            OrderInvoiceSchema::BUYER_IDENTITY => $this->resource->{OrderInvoiceSchema::BUYER_IDENTITY},
            OrderInvoiceSchema::SELLER_IDENTITY => $this->resource->{OrderInvoiceSchema::SELLER_IDENTITY},
            OrderInvoiceSchema::META => $this->resource->{OrderInvoiceSchema::META},
            OrderInvoiceSchema::PDF_STATUS => $this->resource->{OrderInvoiceSchema::PDF_STATUS},
            OrderInvoiceSchema::RES_ITEMS => OrderInvoiceItemResource::collection(
                $this->whenLoaded(OrderInvoiceSchema::RES_ITEMS)
            ),
        ];
    }
}
