<?php

namespace Modules\Order\Transformers\Admin\Invoice;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Core\Support\Money;
use Modules\Order\Models\OrderInvoiceItem;
use Modules\Order\Schemas\OrderInvoiceItem\OrderInvoiceItemSchema;

/**
 * @mixin OrderInvoiceItem
 */
class OrderInvoiceItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        // Money fields render as formatted decimals (see OrderInvoiceResource).
        $money = fn (mixed $value): string => Money::toDecimal(Money::fromDecimal((string) $value));

        return [
            OrderInvoiceItemSchema::ID => $this->resource->{OrderInvoiceItemSchema::ID},
            OrderInvoiceItemSchema::PRODUCT_NAME => $this->resource->{OrderInvoiceItemSchema::PRODUCT_NAME},
            OrderInvoiceItemSchema::PRODUCT_SLUG => $this->resource->{OrderInvoiceItemSchema::PRODUCT_SLUG},
            OrderInvoiceItemSchema::SKU => $this->resource->{OrderInvoiceItemSchema::SKU},
            OrderInvoiceItemSchema::QUANTITY => $this->resource->{OrderInvoiceItemSchema::QUANTITY},
            OrderInvoiceItemSchema::UNIT_PRICE => $money($this->resource->{OrderInvoiceItemSchema::UNIT_PRICE}),
            OrderInvoiceItemSchema::DISCOUNT_AMOUNT => $money($this->resource->{OrderInvoiceItemSchema::DISCOUNT_AMOUNT}),
            OrderInvoiceItemSchema::TAX_CLASS_NAME => $this->resource->{OrderInvoiceItemSchema::TAX_CLASS_NAME},
            OrderInvoiceItemSchema::TAX_RATE => $this->resource->{OrderInvoiceItemSchema::TAX_RATE},
            OrderInvoiceItemSchema::TAX_AMOUNT => $money($this->resource->{OrderInvoiceItemSchema::TAX_AMOUNT}),
            OrderInvoiceItemSchema::LINE_TOTAL => $money($this->resource->{OrderInvoiceItemSchema::LINE_TOTAL}),
        ];
    }
}
