<?php

namespace Modules\User\Http\Controllers\User\Address;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\User\Actions\User\Address\ShowAddressAction;
use Modules\User\Transformers\User\Address\AddressResource;

class ShowAddressController extends Controller
{
    public function __construct(protected ShowAddressAction $action) {}

    public function __invoke(string $id, Request $request): AddressResource
    {
        return AddressResource::make($this->action->handle($id));
    }
}
