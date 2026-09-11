<?php

namespace Modules\User\Actions\User\WishlistItem;

use Illuminate\Database\QueryException;
use Modules\Core\Utils\Auth;
use Modules\User\Models\WishlistItem;
use Modules\User\Schemas\WishlistItem\WishlistItemSchema;

class CreateWishlistItemAction
{
    public function __construct(protected WishlistItem $wishlistItem) {}

    /**
     * Idempotent add: saving an already-saved product returns the
     * existing row. The unique index is the ultimate guard — a
     * concurrent duplicate insert loses the race and re-fetches.
     */
    public function handle(array $data): WishlistItem
    {
        $attributes = [
            WishlistItemSchema::USER_ID => Auth::id(),
            WishlistItemSchema::PRODUCT_ID => $data[WishlistItemSchema::PRODUCT_ID],
        ];

        try {
            return $this->wishlistItem->query()->firstOrCreate($attributes);
        } catch (QueryException) {
            return $this->wishlistItem->query()->where($attributes)->firstOrFail();
        }
    }
}
