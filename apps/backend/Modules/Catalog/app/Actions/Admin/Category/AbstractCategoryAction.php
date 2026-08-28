<?php

namespace Modules\Catalog\Actions\Admin\Category;

use Modules\Catalog\Models\Category;

class AbstractCategoryAction
{
    public function __construct(protected Category $model) {}
}
