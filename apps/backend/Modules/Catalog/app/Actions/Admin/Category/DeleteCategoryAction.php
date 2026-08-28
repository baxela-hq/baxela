<?php

namespace Modules\Catalog\Actions\Admin\Category;

class DeleteCategoryAction extends AbstractCategoryAction
{
    public function handle(string $id): bool
    {
        $record = $this->model->query()->findOrFail($id);

        return $record->delete();
    }
}
