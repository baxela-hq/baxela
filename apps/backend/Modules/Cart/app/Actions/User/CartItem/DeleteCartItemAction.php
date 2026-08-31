<?php

namespace Modules\Cart\Actions\User\CartItem;

use Modules\Cart\Schemas\CartItem\CartItemSchema;
use Modules\Core\Contracts\Events\Cart\CartItemRemovedEvent;

class DeleteCartItemAction extends AbstractCartItemAction
{
    public function handle(string $id): bool
    {
        $record = $this->cartItem
            ->query()
            ->where(CartItemSchema::CART_ID, $this->getCartId())
            ->findOrFail($id);
        $deleted = $record->delete();

        if ($deleted) {
            event(CartItemRemovedEvent::fill($record->toArray()));
        }

        return $deleted;
    }
}
