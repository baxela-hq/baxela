<?php

namespace Modules\Content\Schemas\PostCategory;

use Modules\Content\Schemas\Module;

class PostCategoryPostSchema
{
    public const string TABLE = Module::DB_PREFIX.'post_category_post';

    public const string POST_ID = 'post_id';

    public const string POST_CATEGORY_ID = 'post_category_id';
}
