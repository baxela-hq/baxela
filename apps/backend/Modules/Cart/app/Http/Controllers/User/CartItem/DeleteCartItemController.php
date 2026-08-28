<?php

namespace Modules\Cart\Http\Controllers\User\CartItem;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Cart\Actions\User\CartItem\DeleteCartItemAction;
use Symfony\Component\HttpFoundation\Response;

class DeleteCartItemController extends Controller
{
    public function __construct(protected DeleteCartItemAction $action) {}

    public function __invoke(string $id, Request $request): \Illuminate\Http\Response
    {
        $this->action->handle($id);

        return response()->noContent(Response::HTTP_NO_CONTENT);
    }
}
