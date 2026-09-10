<?php

namespace Modules\Cart\Actions\Public\CartItem;

use Modules\Cart\Schemas\CartItem\CartItemSchema;
use Modules\Core\Contracts\Events\Cart\CartItemRemovedEvent;

class DeleteCartItemAction extends AbstractCartItemAction
{
    public function handle(string $token, string $id): bool
    {
        // A cart that was never created has no items to delete — the scoped
        // findOrFail turns that into the same 404 as an unknown item id.
        $cartId = $this->findCartId($token) ?? 0;

        $record = $this->cartItem
            ->query()
            ->where(CartItemSchema::CART_ID, $cartId)
            ->findOrFail($id);
        $deleted = $record->delete();

        if ($deleted) {
            event(CartItemRemovedEvent::fill($record->toArray()));
        }

        return $deleted;
    }
}
