<?php

namespace Modules\Cart\Actions\Public\CartItem;

use Modules\Cart\Schemas\CartItem\CartItemSchema;
use Modules\Catalog\Schemas\OptionValue\OptionValueSchema;
use Modules\Catalog\Schemas\Product\ProductSchema;
use Modules\Catalog\Schemas\Variant\VariantSchema;

class ListCartItemAction extends AbstractCartItemAction
{
    /**
     * A guest without a cart simply has an empty cart — no row is created
     * just by viewing it.
     */
    public function handle(string $token)
    {
        $cartId = $this->findCartId($token);
        if ($cartId === null) {
            return $this->cartItem->newCollection();
        }

        return $this->cartItem
            ->where(CartItemSchema::CART_ID, $cartId)
            ->with([
                CartItemSchema::RES_VARIANT.'.'.VariantSchema::RES_OPTION_VALUES.'.'.OptionValueSchema::RES_TRANSLATIONS,
                CartItemSchema::RES_VARIANT.'.product.'.ProductSchema::RES_TRANSLATIONS,
            ])
            ->get();
    }
}
