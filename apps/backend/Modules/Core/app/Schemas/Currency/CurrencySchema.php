<?php

namespace Modules\Core\Schemas\Currency;

use Modules\Core\Schemas\Module;
use Modules\Core\Schemas\Shared\PkAndTimestampsTrait;

class CurrencySchema
{
    use PkAndTimestampsTrait;

    public const string TABLE = Module::DB_PREFIX.'currencies';

    public const string CODE = 'code';

    public const string NAME = 'name';

    public const string NATIVE_NAME = 'native_name';

    public const string DECIMAL_PLACES = 'decimal_places';

    public const string SYMBOL = 'symbol';

    public const string IS_DEFAULT = 'is_default';

    public const string IS_SYMBOL_RIGHT = 'is_symbol_right';
}
