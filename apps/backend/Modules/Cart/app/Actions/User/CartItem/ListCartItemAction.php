<?php

namespace Modules\Cart\Actions\User\CartItem;

use Modules\Cart\Schemas\CartItem\CartItemSchema;

class ListCartItemAction extends AbstractCartItemAction
{
    public function handle()
    {
        return $this->cartItem->where(CartItemSchema::CART_ID, $this->getCartId())->get();
    }
}
