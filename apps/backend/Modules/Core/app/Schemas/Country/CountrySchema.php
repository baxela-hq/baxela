<?php

namespace Modules\Core\Schemas\Country;

use Modules\Core\Schemas\Module;
use Modules\Core\Schemas\Shared\PkAndTimestampsTrait;

class CountrySchema
{
    use PkAndTimestampsTrait;

    public const string TABLE = Module::DB_PREFIX.'countries';

    public const string CODE = 'code';

    public const string CODE3 = 'code3';

    public const string NAME = 'name';

    public const string NATIVE_NAME = 'native_name';

    public const string PHONE_CODE = 'phone_code';

    public const string EMOJI = 'emoji';
}
