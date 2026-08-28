<?php

namespace Modules\Catalog\Schemas\OptionValue;

use Modules\Catalog\Schemas\Module;
use Modules\Core\Schemas\Shared\PkAndTimestampsTrait;

class OptionValueTranslationSchema
{
    use PkAndTimestampsTrait;

    public const string TABLE = Module::DB_PREFIX.'option_value_translations';

    public const string OPTION_VALUE_ID = 'option_value_id';

    public const string LANGUAGE_ID = 'language_id';

    public const string TITLE = 'title';

    public const string SLUG = 'slug';

    public const string COL_LANGUAGE = 'language';

    public const string REQ_LANGUAGE = 'language';
}
