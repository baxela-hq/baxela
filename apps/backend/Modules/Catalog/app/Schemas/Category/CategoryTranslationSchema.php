<?php

namespace Modules\Catalog\Schemas\Category;

use Modules\Catalog\Schemas\Module;
use Modules\Core\Schemas\Shared\PkAndTimestampsTrait;

class CategoryTranslationSchema
{
    use PkAndTimestampsTrait;

    public const string TABLE = Module::DB_PREFIX.'category_translations';

    public const string CATEGORY_ID = 'category_id';

    public const string LANGUAGE_ID = 'language_id';

    public const string TITLE = 'title';

    public const string SLUG = 'slug';

    public const string DESCRIPTION = 'description';

    public const string COL_LANGUAGE = 'language';

    public const string REQ_LANGUAGE = 'language';
}
