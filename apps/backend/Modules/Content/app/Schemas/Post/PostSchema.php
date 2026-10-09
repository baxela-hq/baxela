<?php

namespace Modules\Content\Schemas\Post;

use Modules\Content\Schemas\Module;
use Modules\Core\Schemas\Shared\PkAndTimestampsTrait;

class PostSchema
{
    use PkAndTimestampsTrait;

    public const string TABLE = Module::DB_PREFIX.'posts';

    public const string STATUS = 'status';

    public const string PUBLISHED_AT = 'published_at';

    public const string RES_TRANSLATIONS = 'translations';

    public const string RES_CATEGORIES = 'categories';

    public const string RES_PRODUCTS = 'products';

    public const string RES_SEO = 'seo';

    public const string RES_COMMENTS = 'comments';
}
