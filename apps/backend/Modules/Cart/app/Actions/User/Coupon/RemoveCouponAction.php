<?php

namespace Modules\Cart\Actions\User\Coupon;

use Modules\Cart\Models\Cart;
use Modules\Cart\Schemas\Cart\CartSchema;
use Modules\Core\Utils\Auth;

class RemoveCouponAction
{
    public function handle(): void
    {
        $cart = Cart::query()->where(CartSchema::USER_ID, Auth::id())->first();

        if (is_null($cart) || is_null($cart->{CartSchema::COUPON_CODE})) {
            return;
        }

        $cart->{CartSchema::COUPON_CODE} = null;
        $cart->save();
    }
}
