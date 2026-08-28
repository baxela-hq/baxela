<?php

namespace Modules\User\Actions\User\Address;

use Illuminate\Database\Eloquent\Model;

class ShowAddressAction extends AbstractAddressAction
{
    public function handle(string $id): ?Model
    {
        return $this->model->findOrFail($id);
    }
}
