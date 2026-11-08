<?php

namespace Modules\Tax\Schemas\TaxRate;

use Modules\Core\Schemas\Shared\PkAndTimestampsTrait;
use Modules\Tax\Schemas\Module;

class TaxRateSchema
{
    use PkAndTimestampsTrait;

    public const string TABLE = Module::DB_PREFIX.'rates';

    public const string NAME = 'name';

    public const string COUNTRY_CODE = 'country_code';

    /**
     * ISO 3166-2 subdivision code ('US-CA'), NOT NULL with '' as the
     * country-wide scope — the scope key of the uniqueness constraint
     * (gap-C1a D1). '' never reaches the API: actions normalize an
     * omitted region to '', resources expose null for ''.
     */
    public const string REGION_CODE = 'region_code';

    public const string TAX_CLASS_ID = 'tax_class_id';

    /**
     * Percentage with 4 fraction digits (decimal(7,4)); parsed to integer
     * rate-units (percent × 10^4) for exact calculation — never a float.
     */
    public const string RATE = 'rate';

    public const string IS_ACTIVE = 'is_active';

    /** Eloquent relation name (camelCase, matches the model method). */
    public const string RELATION_TAX_CLASS = 'taxClass';

    /** Wire format key for the embedded class (snake_case like the rest). */
    public const string RES_TAX_CLASS = 'tax_class';
}
