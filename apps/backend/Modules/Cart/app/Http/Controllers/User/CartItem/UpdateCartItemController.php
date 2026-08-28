<?php

namespace Modules\Cart\Http\Controllers\User\CartItem;

use App\Http\Controllers\Controller;
use Modules\Cart\Actions\User\CartItem\UpdateCartItemAction;
use Modules\Cart\Http\Requests\User\CartItem\UpdateCartItemRequest;
use Modules\Cart\Transformers\User\CartItem\CartItemResource;

class UpdateCartItemController extends Controller
{
    public function __construct(protected UpdateCartItemAction $action) {}

    public function __invoke(string $id, UpdateCartItemRequest $request): CartItemResource
    {
        return CartItemResource::make($this->action->handle($id, $request));
    }
}
