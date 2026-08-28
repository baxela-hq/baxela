<?php

namespace Modules\Catalog\Schemas\Product;

use Modules\Catalog\Schemas\Module;
use Modules\Core\Schemas\Shared\PkTrait;

class ProductAttributeValueSchema
{
    use PkTrait;

    public const string TABLE = Module::DB_PREFIX.'product_attribute_values';

    public const string PRODUCT_ID = 'product_id';

    public const string ATTRIBUTE_ID = 'attribute_id';

    public const string ATTRIBUTE_VALUE_ID = 'attribute_value_id';

    public const string TEXT_VALUE = 'text_value';

    public const string NUMBER_VALUE = 'number_value';

    public const string BOOLEAN_VALUE = 'boolean_value';

    public const string RES_ATTRIBUTE = 'attribute';

    public const string RES_ATTRIBUTE_VALUE = 'attributeValue';

    public const string REQ_ATTRIBUTE_VALUES = 'attribute_values';
}
