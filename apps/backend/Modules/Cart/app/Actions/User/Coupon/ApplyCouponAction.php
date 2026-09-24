<?php

namespace Modules\Cart\Actions\User\Coupon;

use Modules\Cart\Exceptions\User\Checkout\EmptyCardException;
use Modules\Cart\Exceptions\User\Coupon\InvalidCouponException;
use Modules\Cart\Models\Cart;
use Modules\Cart\Schemas\Cart\CartSchema;
use Modules\Cart\Support\CartSubtotal;
use Modules\Core\Contracts\Gateways\Discount\DiscountGatewayInterface;
use Modules\Core\Contracts\Gateways\Discount\DTOs\CouponDiscountResult;
use Modules\Core\Utils\Auth;

class ApplyCouponAction
{
    public function __construct(protected DiscountGatewayInterface $discountGateway) {}

    /**
     * Evaluate the requested code against the live cart and store it as the
     * cart's single coupon. Exactly one coupon per cart: a valid new code
     * replaces the old one; an invalid new code throws and leaves any
     * previously applied coupon untouched.
     *
     * @return CouponDiscountResult the applied coupon with its computed discount
     *
     * @throws EmptyCardException
     * @throws InvalidCouponException
     */
    public function handle(string $code): CouponDiscountResult
    {
        $cart = Cart::query()->where(CartSchema::USER_ID, Auth::id())->first();
        $cartItems = $cart?->items;

        if (is_null($cart) || $cartItems->isEmpty()) {
            throw new EmptyCardException;
        }

        $evaluation = $this->discountGateway->evaluate(
            $code,
            CartSubtotal::minor($cartItems),
            (int) Auth::id()
        );

        if (! $evaluation->isEligible()) {
            throw new InvalidCouponException($evaluation->failure);
        }

        $cart->{CartSchema::COUPON_CODE} = $evaluation->discount->code;
        $cart->save();

        return $evaluation->discount;
    }
}
