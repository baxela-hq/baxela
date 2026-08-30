<?php

namespace Modules\Shipping\Schemas\Zone;

use Modules\Core\Schemas\Shared\PkAndTimestampsTrait;
use Modules\Shipping\Schemas\Module;

class ZoneSchema
{
    use PkAndTimestampsTrait;

    public const string TABLE = Module::DB_PREFIX.'zones';

    public const string NAME = 'name';

    public const string IS_ACTIVE = 'is_active';

    public const string POSITION = 'position';

    public const string RES_COUNTRIES = 'countries';

    public const string COUNTRY_CODES = 'country_codes';
}
