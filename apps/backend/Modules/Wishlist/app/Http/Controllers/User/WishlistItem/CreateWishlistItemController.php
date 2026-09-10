<?php

namespace Modules\Wishlist\Http\Controllers\User\WishlistItem;

use App\Http\Controllers\Controller;
use Modules\Wishlist\Actions\User\WishlistItem\CreateWishlistItemAction;
use Modules\Wishlist\Http\Requests\User\WishlistItem\WishlistItemRequest;
use Modules\Wishlist\Transformers\User\WishlistItem\WishlistItemResource;

class CreateWishlistItemController extends Controller
{
    public function __construct(protected CreateWishlistItemAction $action) {}

    public function __invoke(WishlistItemRequest $request): WishlistItemResource
    {
        return WishlistItemResource::make($this->action->handle($request->validated()));
    }
}
