<?php

namespace Modules\Order\Schemas\OrderInvoiceItem;

use Modules\Core\Schemas\Shared\PkAndTimestampsTrait;
use Modules\Order\Schemas\Module;

class OrderInvoiceItemSchema
{
    use PkAndTimestampsTrait;

    public const string TABLE = Module::DB_PREFIX.'invoice_items';

    public const string INVOICE_ID = 'invoice_id';

    public const string PRODUCT_NAME = 'product_name';

    public const string PRODUCT_SLUG = 'product_slug';

    public const string SKU = 'sku';

    public const string VARIANT_ID = 'variant_id';

    public const string QUANTITY = 'quantity';

    /**
     * Line snapshot semantics (D11, v2.2): every money column is COPIED
     * from the finalized order-item snapshot at issue time — the invoice
     * never recalculates checkout rounding or allocation decisions.
     * unit_price = per-unit price actually charged before line-level
     * promotion; discount_amount = promotion_discount +
     * coupon_discount_allocated; tax/line totals are the order item's
     * finalized figures.
     */
    public const string UNIT_PRICE = 'unit_price';

    public const string PROMOTION_DISCOUNT = 'promotion_discount';

    public const string COUPON_DISCOUNT_ALLOCATED = 'coupon_discount_allocated';

    public const string DISCOUNT_AMOUNT = 'discount_amount';

    public const string TAX_CLASS_ID = 'tax_class_id';

    public const string TAX_CLASS_NAME = 'tax_class_name';

    public const string TAX_RATE = 'tax_rate';

    public const string TAX_AMOUNT = 'tax_amount';

    public const string LINE_TOTAL = 'line_total';

    public const string META = 'meta';
}
