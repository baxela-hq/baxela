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

    /**
     * Display-conversion rate against the base (default) currency as a
     * decimal(16,8) string — 1 base unit = rate display units. Kept at 1
     * for the default currency itself; admin-managed, no external FX feed.
     */
    public const string EXCHANGE_RATE = 'exchange_rate';

    public const string IS_ACTIVE = 'is_active';
}
