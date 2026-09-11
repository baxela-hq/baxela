<?php

namespace Modules\User\Http\Controllers\User\WishlistItem;

use App\Http\Controllers\Controller;
use Modules\User\Actions\User\WishlistItem\CreateWishlistItemAction;
use Modules\User\Http\Requests\User\WishlistItem\WishlistItemRequest;
use Modules\User\Transformers\User\WishlistItem\WishlistItemResource;

class CreateWishlistItemController extends Controller
{
    public function __construct(protected CreateWishlistItemAction $action) {}

    public function __invoke(WishlistItemRequest $request): WishlistItemResource
    {
        return WishlistItemResource::make($this->action->handle($request->validated()));
    }
}
