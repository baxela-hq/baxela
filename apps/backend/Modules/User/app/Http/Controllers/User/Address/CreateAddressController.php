<?php

namespace Modules\User\Http\Controllers\User\Address;

use App\Http\Controllers\Controller;
use Modules\User\Actions\User\Address\CreateAddressAction;
use Modules\User\Http\Requests\User\Address\AddressRequest;
use Modules\User\Transformers\User\Address\AddressResource;

class CreateAddressController extends Controller
{
    public function __construct(protected CreateAddressAction $action) {}

    public function __invoke(AddressRequest $request): AddressResource
    {
        return AddressResource::make($this->action->handle($request));
    }
}
