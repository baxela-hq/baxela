<?php

namespace Modules\Order\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Order\Schemas\OrderInvoiceItem\OrderInvoiceItemSchema;

/**
 * One line of an issued invoice — a frozen copy of the order item's
 * finalized figures (D11: tax_amount and line_total are copied, never
 * recalculated at issue time).
 *
 * @mixin Builder
 */
class OrderInvoiceItem extends Model
{
    protected $table = OrderInvoiceItemSchema::TABLE;

    protected $fillable = [
        OrderInvoiceItemSchema::INVOICE_ID,
        OrderInvoiceItemSchema::PRODUCT_NAME,
        OrderInvoiceItemSchema::PRODUCT_SLUG,
        OrderInvoiceItemSchema::SKU,
        OrderInvoiceItemSchema::VARIANT_ID,
        OrderInvoiceItemSchema::QUANTITY,
        OrderInvoiceItemSchema::UNIT_PRICE,
        OrderInvoiceItemSchema::PROMOTION_DISCOUNT,
        OrderInvoiceItemSchema::COUPON_DISCOUNT_ALLOCATED,
        OrderInvoiceItemSchema::DISCOUNT_AMOUNT,
        OrderInvoiceItemSchema::TAX_CLASS_ID,
        OrderInvoiceItemSchema::TAX_CLASS_NAME,
        OrderInvoiceItemSchema::TAX_RATE,
        OrderInvoiceItemSchema::TAX_AMOUNT,
        OrderInvoiceItemSchema::LINE_TOTAL,
        OrderInvoiceItemSchema::META,
    ];

    protected function casts(): array
    {
        return [
            OrderInvoiceItemSchema::META => 'array',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(OrderInvoice::class, OrderInvoiceItemSchema::INVOICE_ID);
    }
}
