<?php

namespace Modules\User\Actions\User\Address;

use Illuminate\Database\Eloquent\Collection;
use Modules\User\Models\User\Address;

class ListAddressAction extends AbstractAddressAction
{
    /** @return Collection<int, Address> */
    public function handle(): Collection
    {
        return $this->model->all();
    }
}
