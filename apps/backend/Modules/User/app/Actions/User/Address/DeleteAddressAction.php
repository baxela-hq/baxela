<?php

namespace Modules\User\Actions\User\Address;

class DeleteAddressAction extends AbstractAddressAction
{
    public function handle(string $id): bool
    {
        $record = $this->model->findOrFail($id);

        return $record->delete();
    }
}
