<?php

namespace Modules\User\Schemas\Address;

use Modules\Core\Schemas\Shared\PkAndTimestampsTrait;
use Modules\User\Schemas\Module;

class AddressSchema
{
    use PkAndTimestampsTrait;

    public const string TABLE = Module::DB_PREFIX.'addresses';

    public const string USER_ID = 'user_id';

    public const string TYPE = 'type';

    public const string FULL_NAME = 'full_name';

    public const string PHONE = 'phone';

    public const string ADDRESS_LINE = 'address_line';

    public const string CITY = 'city';

    public const string POSTAL_CODE = 'postal_code';

    public const string COUNTRY_CODE = 'country_code';

    /** ISO 3166-2 ('US-CA'); nullable — only new input is validated. */
    public const string REGION_CODE = 'region_code';

    /** Business VAT identifier (gap-C1c); offline format-validated only. */
    public const string VAT_ID = 'vat_id';

    public const string IS_DEFAULT = 'is_default';
}
