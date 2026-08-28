<?php

namespace Modules\Core\Schemas\Language;

use Modules\Core\Schemas\Module;
use Modules\Core\Schemas\Shared\PkAndTimestampsTrait;

class LanguageSchema
{
    use PkAndTimestampsTrait;

    public const string TABLE = Module::DB_PREFIX.'languages';

    public const string LOCALE = 'locale';

    public const string NAME = 'name';

    public const string NATIVE_NAME = 'native_name';

    public const string CODE = 'code';

    public const string CODE3 = 'code3';

    public const string IS_RTL = 'is_rtl';

    public const string IS_ACTIVE = 'is_active';

    public const string IS_DEFAULT = 'is_default';

    public const string POSITION = 'position';

    public const string DATE_FORMAT = 'date_format';

    public const string TIME_FORMAT = 'time_format';
}
