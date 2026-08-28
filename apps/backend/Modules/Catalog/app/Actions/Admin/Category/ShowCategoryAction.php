<?php

namespace Modules\Catalog\Actions\Admin\Category;

use Illuminate\Database\Eloquent\Model;
use Modules\Catalog\Schemas\Category\CategorySchema;

class ShowCategoryAction extends AbstractCategoryAction
{
    public function handle(string $id): Model
    {
        return $this->model->query()->with(CategorySchema::RES_TRANSLATIONS)->findOrFail($id);
    }
}
