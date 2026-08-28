<?php

namespace Modules\Cart\Http\Controllers\User\CartItem;

use App\Http\Controllers\Controller;
use Modules\Cart\Actions\User\CartItem\CreateCartItemAction;
use Modules\Cart\Http\Requests\User\CartItem\CreateCartItemRequest;
use Modules\Cart\Transformers\User\CartItem\CartItemResource;

class CreateCartItemController extends Controller
{
    public function __construct(protected CreateCartItemAction $action) {}

    public function __invoke(CreateCartItemRequest $request): CartItemResource
    {
        return CartItemResource::make($this->action->handle($request));
    }
}
