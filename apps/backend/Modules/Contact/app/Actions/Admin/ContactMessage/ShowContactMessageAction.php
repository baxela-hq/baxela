<?php

namespace Modules\Contact\Actions\Admin\ContactMessage;

use Illuminate\Database\Eloquent\Model;

class ShowContactMessageAction extends AbstractContactMessageAction
{
    public function handle(string $id): Model
    {
        return $this->model->query()->findOrFail($id);
    }
}
