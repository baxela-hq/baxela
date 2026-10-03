<?php

namespace Modules\Content\Actions\Admin\PostCategory;

use Illuminate\Database\Eloquent\Model;
use Modules\Content\Schemas\PostCategory\PostCategorySchema;

class ShowPostCategoryAction extends AbstractPostCategoryAction
{
    public function handle(string $id): Model
    {
        return $this->model->query()->with(PostCategorySchema::RES_TRANSLATIONS)->findOrFail($id);
    }
}
