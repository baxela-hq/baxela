<?php

namespace Modules\Content\Actions\Public\PostCategory;

use Modules\Content\Models\PostCategory;

abstract class AbstractPostCategoryAction
{
    public function __construct(protected PostCategory $model) {}
}
