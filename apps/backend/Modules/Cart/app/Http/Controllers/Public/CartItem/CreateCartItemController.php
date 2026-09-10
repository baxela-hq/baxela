<?php

namespace Modules\Cart\Http\Controllers\Public\CartItem;

use App\Http\Controllers\Controller;
use Modules\Cart\Actions\Public\CartItem\CreateCartItemAction;
use Modules\Cart\Http\Middleware\CartTokenMiddleware;
use Modules\Cart\Http\Requests\Public\CartItem\CreateCartItemRequest;
use Modules\Cart\Transformers\Public\CartItem\CartItemResource;

class CreateCartItemController extends Controller
{
    public function __construct(protected CreateCartItemAction $action) {}

    public function __invoke(CreateCartItemRequest $request): CartItemResource
    {
        return CartItemResource::make(
            $this->action->handle($request->header(CartTokenMiddleware::HEADER), $request)
        );
    }
}
