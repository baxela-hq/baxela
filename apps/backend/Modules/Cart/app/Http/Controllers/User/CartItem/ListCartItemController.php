<?php

namespace Modules\Cart\Http\Controllers\User\CartItem;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Cart\Actions\User\CartItem\ListCartItemAction;
use Modules\Cart\Transformers\User\CartItem\CartItemResource;

class ListCartItemController extends Controller
{
    public function __construct(protected ListCartItemAction $action) {}

    public function __invoke(Request $request): AnonymousResourceCollection
    {
        return CartItemResource::collection($this->action->handle());
    }
}
