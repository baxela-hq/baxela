<?php

namespace Modules\Setting\Schemas\Setting;

use Modules\Core\Schemas\Shared\PkAndTimestampsTrait;
use Modules\Setting\Schemas\Module;

class SettingSchema
{
    use PkAndTimestampsTrait;

    public const string TABLE = Module::DB_PREFIX.'settings';

    public const string GROUP = 'group';

    public const string TYPE = 'type';

    public const string NAME = 'name';

    public const string VALUE = 'value';

    public const string IS_TRANSLATABLE = 'is_translatable';

    public const string COMMENT = 'comment';

    public const string RES_TRANSLATIONS = 'translations';
}
