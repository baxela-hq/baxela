<?php

namespace Modules\Catalog\Schemas\Category;

use Modules\Catalog\Schemas\Module;

class CategoryAttributeSchema
{
    public const string TABLE = Module::DB_PREFIX.'category_attribute';

    public const string CATEGORY_ID = 'category_id';

    public const string ATTRIBUTE_ID = 'attribute_id';

    public const string POSITION = 'position';

    public const string REQ_ATTRIBUTE_IDS = 'attribute_ids';
}
