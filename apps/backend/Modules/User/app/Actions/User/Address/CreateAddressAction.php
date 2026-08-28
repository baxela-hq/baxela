<?php

namespace Modules\User\Actions\User\Address;

use Modules\User\Http\Requests\User\Address\AddressRequest;
use Modules\User\Schemas\Address\AddressSchema;

class CreateAddressAction extends AbstractAddressAction
{
    public function handle(AddressRequest $request)
    {
        if ($request->input(AddressSchema::IS_DEFAULT)) {
            $this->model->query()->update([
                AddressSchema::IS_DEFAULT => false,
            ]);
        }
        $record = $this->model->create($request->validated());

        return $record->refresh();
    }
}
