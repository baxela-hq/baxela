<?php

namespace Modules\Shipping\Schemas\Rate;

use Modules\Core\Schemas\Shared\PkAndTimestampsTrait;
use Modules\Shipping\Schemas\Module;

class RateSchema
{
    use PkAndTimestampsTrait;

    public const string TABLE = Module::DB_PREFIX.'rates';

    public const string METHOD_ID = 'method_id';

    public const string ZONE_ID = 'zone_id';

    public const string PRICE = 'price';
}
