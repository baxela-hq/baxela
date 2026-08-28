<?php

namespace Modules\Catalog\Actions\Public\Category;

class ListCategoryAction extends AbstractCategoryAction
{
    public function handle()
    {
        return $this->model
            ->paginate(15)
            ->withQueryString();
    }
}
