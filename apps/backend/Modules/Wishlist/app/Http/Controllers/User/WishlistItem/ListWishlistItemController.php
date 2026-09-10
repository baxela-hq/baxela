<?php

namespace Modules\Wishlist\Http\Controllers\User\WishlistItem;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Wishlist\Actions\User\WishlistItem\ListWishlistItemAction;
use Modules\Wishlist\Transformers\User\WishlistItem\WishlistItemResource;

class ListWishlistItemController extends Controller
{
    public function __construct(protected ListWishlistItemAction $action) {}

    public function __invoke(Request $request)
    {
        return WishlistItemResource::collection($this->action->handle());
    }
}
