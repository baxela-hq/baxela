<?php

namespace Modules\Wishlist\Actions\User\WishlistItem;

use Modules\Core\Utils\Auth;
use Modules\Wishlist\Models\WishlistItem;
use Modules\Wishlist\Schemas\WishlistItem\WishlistItemSchema;

class DeleteWishlistItemAction
{
    public function __construct(protected WishlistItem $wishlistItem) {}

    /**
     * Keyed by product id — the user's mental model is "remove product X
     * from my wishlist" and (user, product) is the natural key.
     */
    public function handle(string $productId): bool
    {
        $record = $this->wishlistItem
            ->query()
            ->where([
                WishlistItemSchema::USER_ID => Auth::id(),
                WishlistItemSchema::PRODUCT_ID => $productId,
            ])
            ->firstOrFail();

        return (bool) $record->delete();
    }
}
