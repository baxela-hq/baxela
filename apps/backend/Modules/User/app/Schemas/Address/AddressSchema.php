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

    public const string IS_DEFAULT = 'is_default';
}
