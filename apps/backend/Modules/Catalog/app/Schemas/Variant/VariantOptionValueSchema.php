<?php

namespace Modules\Catalog\Schemas\Variant;

use Modules\Catalog\Schemas\Module;
use Modules\Core\Schemas\Shared\PkTrait;

class VariantOptionValueSchema
{
    use PkTrait;

    public const string TABLE = Module::DB_PREFIX.'variant_option_values';

    public const string VARIANT_ID = 'variant_id';

    public const string OPTION_VALUE_ID = 'option_value_id';
}
