<?php

namespace Modules\Content\Schemas\Page;

use Modules\Content\Schemas\Module;
use Modules\Core\Schemas\Shared\PkAndTimestampsTrait;

class PageTranslationSchema
{
    use PkAndTimestampsTrait;

    public const string TABLE = Module::DB_PREFIX.'page_translations';

    public const string PAGE_ID = 'page_id';

    public const string LANGUAGE_ID = 'language_id';

    public const string TITLE = 'title';

    public const string SLUG = 'slug';

    public const string CONTENT = 'content';

    public const string DESCRIPTION = 'description';

    public const string COL_LANGUAGE = 'language';

    public const string REQ_LANGUAGE = 'language';
}
