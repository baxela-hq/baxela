<?php

namespace Modules\Auth\Actions\Admin\User;

class DeleteUserAction extends AbstractUserAction
{
    public function handle(string $id): bool
    {
        $record = $this->model->query()->findOrFail($id);

        return $record->delete();
    }
}
