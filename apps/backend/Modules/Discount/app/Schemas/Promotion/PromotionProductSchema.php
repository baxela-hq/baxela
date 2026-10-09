<?php

namespace Modules\Discount\Schemas\Promotion;

use Modules\Discount\Schemas\Module;

class PromotionProductSchema
{
    public const string TABLE = Module::DB_PREFIX.'promotion_products';

    public const string PROMOTION_ID = 'promotion_id';

    /**
     * Plain catalog product id — deliberately no cross-module FK.
     */
    public const string PRODUCT_ID = 'product_id';
}
