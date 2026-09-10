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

    public const string QUANTITY = 'quantity';

    public const string IMAGE_URL = 'image_url';

    /**
     * Runtime-only attribute: the VariantSummary DTO attached by the list
     * action (never stored — variant data is resolved through the Catalog
     * gateway, not a relation).
     */
    public const string ATTR_VARIANT_SUMMARY = 'variantSummary';
}
