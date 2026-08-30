<?php

namespace Modules\Shipping\Schemas\Zone;

use Modules\Shipping\Schemas\Module;

class ZoneCountrySchema
{
    public const string TABLE = Module::DB_PREFIX.'zone_countries';

    public const string ZONE_ID = 'zone_id';

    public const string COUNTRY_CODE = 'country_code';
}
