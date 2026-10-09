<?php

namespace Modules\Discount\Schemas\Promotion;

use Modules\Discount\Schemas\Module;

class PromotionCategorySchema
{
    public const string TABLE = Module::DB_PREFIX.'promotion_categories';

    public const string PROMOTION_ID = 'promotion_id';

    /**
     * Plain catalog category id — deliberately no cross-module FK.
     */
    public const string CATEGORY_ID = 'category_id';
}
