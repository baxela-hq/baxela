<?php

namespace Modules\User\Actions\User\Address;

use Illuminate\Database\Eloquent\Model;
use Modules\User\Http\Requests\User\Address\AddressRequest;
use Modules\User\Schemas\Address\AddressSchema;

class UpdateAddressAction extends AbstractAddressAction
{
    public function handle(string $id, AddressRequest $request): Model
    {
        $record = $this->model->findOrFail($id);

        if ($record->{AddressSchema::IS_DEFAULT} !== $request->input(AddressSchema::IS_DEFAULT)) {
            if ($request->input(AddressSchema::IS_DEFAULT)) {
                $this->model->query()->update([
                    AddressSchema::IS_DEFAULT => ! $request->input(AddressSchema::IS_DEFAULT),
                ]);
            }
        }

        $record->update($request->validated());

        return $record->refresh();
    }
}
