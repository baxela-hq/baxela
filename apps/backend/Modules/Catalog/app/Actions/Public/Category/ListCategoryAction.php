<?php

namespace Modules\Catalog\Actions\Public\Category;

use Modules\Catalog\Schemas\Category\CategorySchema;

class ListCategoryAction extends AbstractCategoryAction
{
    public function handle()
    {
        return $this->model
            ->with(CategorySchema::RES_TRANSLATIONS)
            ->paginate(15)
            ->withQueryString();
    }
}
