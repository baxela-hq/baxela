<?php

namespace Modules\Content\Actions\Admin\PostCategory;

class DeletePostCategoryAction extends AbstractPostCategoryAction
{
    public function handle(string $id): bool
    {
        $record = $this->model->findOrFail($id);

        return $record->delete();
    }
}
