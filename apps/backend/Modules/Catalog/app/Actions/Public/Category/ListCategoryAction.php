<?php

namespace Modules\Catalog\Actions\Public\Category;

use Illuminate\Http\Request;
use Modules\Catalog\Schemas\Category\CategorySchema;

class ListCategoryAction extends AbstractCategoryAction
{
    public function handle(Request $request)
    {
        $perPage = min(max((int) $request->input('per_page', 15), 1), 100);

        return $this->model
            ->with(CategorySchema::RES_TRANSLATIONS)
            ->paginate($perPage)
            ->withQueryString();
    }
}
