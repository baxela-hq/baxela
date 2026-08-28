<?php

namespace Modules\Auth\Actions\Admin\User;

use Illuminate\Database\Eloquent\Model;

class ShowUserAction extends AbstractUserAction
{
    public function handle(string $id): Model
    {
        return $this->model->query()->findOrFail($id);
    }
}
