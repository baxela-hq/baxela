<?php

namespace Modules\Catalog\Actions\Public\Category;

use Modules\Catalog\Models\Category;

class AbstractCategoryAction
{
    public function __construct(protected Category $model) {}
}
