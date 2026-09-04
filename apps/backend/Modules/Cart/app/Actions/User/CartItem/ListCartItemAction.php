<?php

namespace Modules\Cart\Actions\User\CartItem;

use Modules\Cart\Schemas\CartItem\CartItemSchema;
use Modules\Catalog\Schemas\OptionValue\OptionValueSchema;
use Modules\Catalog\Schemas\Variant\VariantSchema;

class ListCartItemAction extends AbstractCartItemAction
{
    public function handle()
    {
        return $this->cartItem
            ->where(CartItemSchema::CART_ID, $this->getCartId())
            ->with(CartItemSchema::RES_VARIANT.'.'.VariantSchema::RES_OPTION_VALUES.'.'.OptionValueSchema::RES_TRANSLATIONS)
            ->get();
    }
}
