<?php

namespace Modules\Content\Schemas\PostCategory;

use Modules\Content\Schemas\Module;
use Modules\Core\Schemas\Shared\PkAndTimestampsTrait;

class PostCategorySchema
{
    use PkAndTimestampsTrait;

    public const string TABLE = Module::DB_PREFIX.'post_categories';

    public const string PARENT_ID = 'parent_id';

    public const string POSITION = 'position';

    public const string RES_TRANSLATIONS = 'translations';
}
