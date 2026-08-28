<?php

namespace Modules\User\Http\Controllers\User\Address;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\User\Actions\User\Address\ListAddressAction;
use Modules\User\Transformers\User\Address\AddressResource;

class ListAddressController extends Controller
{
    public function __construct(protected ListAddressAction $action) {}

    public function __invoke(Request $request): AnonymousResourceCollection
    {
        return AddressResource::collection($this->action->handle());
    }
}
