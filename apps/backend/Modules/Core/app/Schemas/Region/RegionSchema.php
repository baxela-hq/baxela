<?php

namespace Modules\Core\Schemas\Region;

use Modules\Core\Schemas\Shared\PkAndTimestampsTrait;

class RegionSchema
{
    use PkAndTimestampsTrait;

    public const string TABLE = 'core_regions';

    public const string COUNTRY_CODE = 'country_code';

    /** Canonical ISO 3166-2 code ('US-CA'). */
    public const string CODE = 'code';

    public const string NAME = 'name';
}
