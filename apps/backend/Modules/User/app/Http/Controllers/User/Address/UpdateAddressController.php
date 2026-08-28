<?php

namespace Modules\User\Http\Controllers\User\Address;

use App\Http\Controllers\Controller;
use Modules\User\Actions\User\Address\UpdateAddressAction;
use Modules\User\Http\Requests\User\Address\AddressRequest;
use Modules\User\Transformers\User\Address\AddressResource;

class UpdateAddressController extends Controller
{
    public function __construct(protected UpdateAddressAction $action) {}

    public function __invoke(string $id, AddressRequest $request): AddressResource
    {
        return AddressResource::make($this->action->handle($id, $request));
    }
}
