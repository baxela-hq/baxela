<?php

namespace Modules\Cart\Support;

use Modules\Cart\Schemas\CartItem\CartItemSchema;
use Modules\Core\Support\Money;

class CartSubtotal
{
    /**
     * Merchandise subtotal in minor units: Σ price_snapshot × quantity,
     * before any discount and excluding shipping. The amount coupon
     * eligibility and discount computation are anchored to.
     */
    public static function minor(iterable $cartItems): int
    {
        $subtotal = 0;
        foreach ($cartItems as $cartItem) {
            $subtotal += Money::fromDecimal((string) $cartItem->{CartItemSchema::PRICE_SNAPSHOT})
                * (int) $cartItem->{CartItemSchema::QUANTITY};
        }

        return $subtotal;
    }
}
