<?php

namespace Modules\User\Http\Controllers\User\WishlistItem;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\User\Actions\User\WishlistItem\DeleteWishlistItemAction;
use Symfony\Component\HttpFoundation\Response;

class DeleteWishlistItemController extends Controller
{
    public function __construct(protected DeleteWishlistItemAction $action) {}

    public function __invoke(string $product_id, Request $request): \Illuminate\Http\Response
    {
        $this->action->handle($product_id);

        return response()->noContent(Response::HTTP_NO_CONTENT);
    }
}
