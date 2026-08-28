<?php

namespace Modules\Catalog\Schemas\Variant;

use Modules\Catalog\Schemas\Module;
use Modules\Core\Schemas\Shared\PkAndTimestampsTrait;

class VariantSchema
{
    use PkAndTimestampsTrait;

    public const string TABLE = Module::DB_PREFIX.'variants';

    public const string PRODUCT_ID = 'product_id';

    public const string SKU = 'sku';

    public const string BARCODE = 'barcode';

    public const string PRICE = 'price';

    public const string QUANTITY = 'quantity';

    public const string COMPARE_PRICE = 'compare_price';

    public const string COST_PRICE = 'cost_price';

    public const string IS_DEFAULT = 'is_default';

    public const string RES_OPTION_VALUES = 'optionValues';

    public const string RES_OPTION_VALUE_IDS = 'option_value_ids';

    public const string REQ_OPTION_VALUE_IDS = 'option_value_ids';
}
