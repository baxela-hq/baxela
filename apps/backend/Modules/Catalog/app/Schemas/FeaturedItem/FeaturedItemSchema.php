<?php

namespace Modules\Catalog\Schemas\FeaturedItem;

use Modules\Catalog\Schemas\Module;
use Modules\Core\Schemas\Shared\PkAndTimestampsTrait;

class FeaturedItemSchema
{
    use PkAndTimestampsTrait;

    public const string TABLE = Module::DB_PREFIX.'featured_items';

    public const string FEATUREDABLE_TYPE = 'featuredable_type';

    public const string FEATUREDABLE_ID = 'featuredable_id';

    public const string POSITION = 'position';

    /** Morph alias stored in FEATUREDABLE_TYPE for products. */
    public const string TYPE_PRODUCT = 'product';

    /** Morph alias stored in FEATUREDABLE_TYPE for categories. */
    public const string TYPE_CATEGORY = 'category';

    public const string RES_PRODUCT = 'product';

    public const string RES_CATEGORY = 'category';

    public const string REQ_PRODUCT_IDS = 'product_ids';

    public const string REQ_CATEGORY_IDS = 'category_ids';
}
