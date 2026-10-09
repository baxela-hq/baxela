<?php

namespace Modules\Order\Schemas\OrderItem;

use Modules\Core\Schemas\Shared\PkAndCreatedAtTrait;
use Modules\Order\Schemas\Module;

class OrderItemSchema
{
    use PkAndCreatedAtTrait;

    public const string TABLE = Module::DB_PREFIX.'order_items';

    public const string ORDER_ID = 'order_id';

    public const string VARIANT_ID = 'variant_id';

    public const string PRODUCT_NAME_SNAPSHOT = 'product_name_snapshot';

    public const string PRODUCT_SLUG_SNAPSHOT = 'product_slug_snapshot';

    public const string PRICE_SNAPSHOT = 'price_snapshot';

    /**
     * Promotion history — filled only for promoted lines so the invoice
     * stays self-contained even after the promotion row is deleted:
     * base_price is the unit price before the promotion, promotion_discount
     * the ACTUAL per-unit amount taken off, promotion_id a convenience
     * pointer. price_snapshot remains the charged unit price; the
     * invariant base_price − promotion_discount == price_snapshot holds.
     */
    public const string BASE_PRICE = 'base_price';

    public const string PROMOTION_DISCOUNT = 'promotion_discount';

    public const string PROMOTION_ID = 'promotion_id';

    public const string QUANTITY = 'quantity';

    public const string IMAGE_URL = 'image_url';

    /**
     * Runtime-only attribute: the VariantSummary DTO attached by the list
     * action (never stored — variant data is resolved through the Catalog
     * gateway, not a relation).
     */
    public const string ATTR_VARIANT_SUMMARY = 'variantSummary';
}
