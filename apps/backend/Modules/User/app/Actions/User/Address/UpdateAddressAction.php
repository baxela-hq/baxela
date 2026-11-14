<?php

namespace Modules\User\Actions\User\Address;

use Modules\User\Http\Requests\User\Address\AddressRequest;
use Modules\User\Models\User\Address;
use Modules\User\Schemas\Address\AddressSchema;

class UpdateAddressAction extends AbstractAddressAction
{
    public function handle(string $id, AddressRequest $request): Address
    {
        $record = $this->model->findOrFail($id);

        if ($record->{AddressSchema::IS_DEFAULT} !== $request->input(AddressSchema::IS_DEFAULT)) {
            if ($request->input(AddressSchema::IS_DEFAULT)) {
                $this->model->query()->update([
                    AddressSchema::IS_DEFAULT => false,
                ]);
            }
        }

        $record->update($request->validated());

        return $record->refresh();
    }
}
