<?php

namespace Modules\Setting\Schemas\Translation;

use Modules\Core\Schemas\Shared\PkAndTimestampsTrait;
use Modules\Setting\Schemas\Module;

class TranslationSchema
{
    use PkAndTimestampsTrait;

    public const string TABLE = Module::DB_PREFIX.'translations';

    public const string SETTING_ID = 'setting_id';

    public const string LANGUAGE_ID = 'language_id';

    public const string LANGUAGE = 'language';

    public const string VALUE = 'value';
}
