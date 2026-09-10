<?php

namespace Modules\Cart\Http\Controllers\Public\CartItem;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Cart\Actions\Public\CartItem\ListCartItemAction;
use Modules\Cart\Http\Middleware\CartTokenMiddleware;
use Modules\Cart\Transformers\Public\CartItem\CartItemResource;

class ListCartItemController extends Controller
{
    public function __construct(protected ListCartItemAction $action) {}

    public function __invoke(Request $request)
    {
        return CartItemResource::collection(
            $this->action->handle($request->header(CartTokenMiddleware::HEADER))
        );
    }
}
