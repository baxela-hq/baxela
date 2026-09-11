<?php

namespace Modules\User\Http\Controllers\User\WishlistItem;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\User\Actions\User\WishlistItem\ListWishlistItemAction;
use Modules\User\Transformers\User\WishlistItem\WishlistItemResource;

class ListWishlistItemController extends Controller
{
    public function __construct(protected ListWishlistItemAction $action) {}

    public function __invoke(Request $request)
    {
        return WishlistItemResource::collection($this->action->handle());
    }
}
