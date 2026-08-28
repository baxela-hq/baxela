<?php

namespace Modules\Catalog\Schemas\Product;

use Modules\Catalog\Schemas\Module;
use Modules\Core\Schemas\Shared\PkAndTimestampsTrait;

class ProductShippingSchema
{
    use PkAndTimestampsTrait;

    public const string TABLE = Module::DB_PREFIX.'product_shipping';

    public const string PRODUCT_ID = 'product_id';

    public const string WEIGHT = 'weight';

    public const string WEIGHT_UNIT = 'weight_unit';

    public const string PACKAGE_LENGTH = 'package_length';

    public const string PACKAGE_WIDTH = 'package_width';

    public const string PACKAGE_HEIGHT = 'package_height';

    public const string DIMENSION_UNIT = 'dimension_unit';

    public const string REQUIRES_SHIPPING = 'requires_shipping';
}
