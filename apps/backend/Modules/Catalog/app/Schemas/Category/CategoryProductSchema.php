<?php

namespace Modules\Catalog\Schemas\Category;

use Modules\Catalog\Schemas\Module;

class CategoryProductSchema
{
    public const string TABLE = Module::DB_PREFIX.'category_product';

    public const string PRODUCT_ID = 'product_id';

    public const string CATEGORY_ID = 'category_id';
}
