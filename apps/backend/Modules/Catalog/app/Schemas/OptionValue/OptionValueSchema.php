<?php

namespace Modules\Catalog\Schemas\OptionValue;

use Modules\Catalog\Schemas\Module;
use Modules\Core\Schemas\Shared\PkAndTimestampsTrait;

class OptionValueSchema
{
    use PkAndTimestampsTrait;

    public const string TABLE = Module::DB_PREFIX.'option_values';

    public const string OPTION_ID = 'option_id';

    public const string POSITION = 'position';

    public const string RES_TRANSLATIONS = 'translations';

    public const string RES_VARIANTS = 'variants';

    public const string RES_PRODUCTS = 'products';
}
