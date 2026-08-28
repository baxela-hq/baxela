<?php

namespace Modules\User\Actions\User\Address;

use Illuminate\Database\Eloquent\Collection;

class ListAddressAction extends AbstractAddressAction
{
    public function handle(): Collection
    {
        return $this->model->all();
    }
}
