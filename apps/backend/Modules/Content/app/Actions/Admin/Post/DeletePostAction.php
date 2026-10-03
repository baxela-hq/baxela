<?php

namespace Modules\Content\Actions\Admin\Post;

class DeletePostAction extends AbstractPostAction
{
    public function handle(string $id): bool
    {
        $record = $this->model->findOrFail($id);

        return $record->delete();
    }
}
