<?php

namespace Modules\Catalog\Schemas\Option;

use Modules\Catalog\Schemas\Module;
use Modules\Core\Schemas\Shared\PkAndTimestampsTrait;

class OptionTranslationSchema
{
    use PkAndTimestampsTrait;

    public const string TABLE = Module::DB_PREFIX.'option_translations';

    public const string OPTION_ID = 'option_id';

    public const string LANGUAGE_ID = 'language_id';

    public const string TITLE = 'title';

    public const string SLUG = 'slug';

    public const string COL_LANGUAGE = 'language';

    public const string REQ_LANGUAGE = 'language';
}
