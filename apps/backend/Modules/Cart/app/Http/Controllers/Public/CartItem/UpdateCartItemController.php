<?php

namespace Modules\Cart\Http\Controllers\Public\CartItem;

use App\Http\Controllers\Controller;
use Modules\Cart\Actions\Public\CartItem\UpdateCartItemAction;
use Modules\Cart\Http\Middleware\CartTokenMiddleware;
use Modules\Cart\Http\Requests\Public\CartItem\UpdateCartItemRequest;
use Modules\Cart\Transformers\Public\CartItem\CartItemResource;

class UpdateCartItemController extends Controller
{
    public function __construct(protected UpdateCartItemAction $action) {}

    public function __invoke(string $id, UpdateCartItemRequest $request): CartItemResource
    {
        return CartItemResource::make(
            $this->action->handle($request->header(CartTokenMiddleware::HEADER), $id, $request)
        );
    }
}
