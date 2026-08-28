<?php

namespace Modules\User\Http\Controllers\User\Address;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\User\Actions\User\Address\DeleteAddressAction;
use Symfony\Component\HttpFoundation\Response;

class DeleteAddressController extends Controller
{
    public function __construct(protected DeleteAddressAction $action) {}

    public function __invoke(string $id, Request $request)
    {
        $this->action->handle($id);

        return response()->noContent(Response::HTTP_NO_CONTENT);
    }
}
