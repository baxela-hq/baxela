<?php

namespace Modules\Cart\Actions\User\Coupon;

use Modules\Cart\Models\Cart;
use Modules\Cart\Schemas\Cart\CartSchema;
use Modules\Cart\Support\CartSubtotal;
use Modules\Core\Contracts\Gateways\Discount\DiscountGatewayInterface;
use Modules\Core\Contracts\Gateways\Discount\DTOs\CouponDiscountResult;
use Modules\Core\Utils\Auth;

class ShowAppliedCouponAction
{
    public function __construct(protected DiscountGatewayInterface $discountGateway) {}

    /**
     * The cart's applied coupon plus its freshly computed discount, or a
     * null result when none applies. Self-healing: when the stored code no
     * longer passes evaluation (deactivated, expired, limit hit since it
     * was applied) the backend clears it and reports no coupon, so the
     * client can never render a stale "applied" state.
     */
    public function handle(): ?CouponDiscountResult
    {
        $cart = Cart::query()->where(CartSchema::USER_ID, Auth::id())->first();
        $couponCode = $cart?->{CartSchema::COUPON_CODE};

        if (is_null($cart) || is_null($couponCode)) {
            return null;
        }

        $evaluation = $this->discountGateway->evaluate(
            $couponCode,
            CartSubtotal::minor($cart->items),
            (int) Auth::id()
        );

        if (! $evaluation->isEligible()) {
            $cart->{CartSchema::COUPON_CODE} = null;
            $cart->save();

            return null;
        }

        return $evaluation->discount;
    }
}
