<?php

namespace Modules\Content\Actions\Admin\Page;

class DeletePageAction extends AbstractPageAction
{
    public function handle(string $id): bool
    {
        $record = $this->model->findOrFail($id);

        return $record->delete();
    }
}
