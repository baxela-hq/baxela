<?php

namespace Modules\Cart\Schemas\CartItem;

use Modules\Cart\Schemas\Module;
use Modules\Core\Schemas\Shared\PkAndTimestampsTrait;

class CartItemSchema
{
    use PkAndTimestampsTrait;

    public const string TABLE = Module::DB_PREFIX.'cart_items';

    public const string CART_ID = 'cart_id';

    public const string VARIANT_ID = 'variant_id';

    public const string QUANTITY = 'quantity';

    public const string PRICE_SNAPSHOT = 'price_snapshot';

    public const string PRODUCT_NAME_SNAPSHOT = 'product_name_snapshot';

    public const string VARIANT_LABEL = 'variant_label';

    public const string PRODUCT_ID = 'product_id';

    public const string PRODUCT_SLUG = 'product_slug';

    public const string RES_VARIANT = 'variant';
}
