<?php

namespace Modules\Cart\Actions\User\Cart;

use Illuminate\Support\Facades\DB;
use Modules\Cart\Exceptions\User\Checkout\EmptyCardException;
use Modules\Cart\Exceptions\User\Checkout\InvalidAddressException;
use Modules\Cart\Exceptions\User\Checkout\InvalidShippingMethodException;
use Modules\Cart\Exceptions\User\Checkout\OrderFailedException;
use Modules\Cart\Exceptions\User\Checkout\OutOfStockException;
use Modules\Cart\Exceptions\User\Coupon\InvalidCouponException;
use Modules\Cart\Http\Requests\User\Cart\CheckoutRequest;
use Modules\Cart\Models\Cart;
use Modules\Cart\Schemas\Cart\CartSchema;
use Modules\Cart\Schemas\CartItem\CartItemSchema;
use Modules\Cart\Support\CartSubtotal;
use Modules\Cart\Support\VariantDisplayName;
use Modules\Core\Contracts\Events\Cart\CartCheckedOutEvent;
use Modules\Core\Contracts\Gateways\Catalog\CatalogGatewayInterface;
use Modules\Core\Contracts\Gateways\Core\CoreGatewayInterface;
use Modules\Core\Contracts\Gateways\Discount\CouponFailureEnum;
use Modules\Core\Contracts\Gateways\Discount\DiscountGatewayInterface;
use Modules\Core\Contracts\Gateways\Discount\RedemptionResult;
use Modules\Core\Contracts\Gateways\Inventory\InventoryGatewayInterface;
use Modules\Core\Contracts\Gateways\Order\DTOs\CreateOrderInput;
use Modules\Core\Contracts\Gateways\Order\OrderGatewayInterface;
use Modules\Core\Contracts\Gateways\Shipping\ShippingGatewayInterface;
use Modules\Core\Contracts\Gateways\User\UserGatewayInterface;
use Modules\Core\Exceptions\Discount\RedemptionRefusedException;
use Modules\Core\Utils\Auth;

class CheckoutAction
{
    public function __construct(protected UserGatewayInterface $userGateway) {}

    /**
     * @return null|string Order code (customer-facing)
     *
     * @throws EmptyCardException
     * @throws InvalidAddressException
     * @throws InvalidShippingMethodException
     * @throws OrderFailedException
     * @throws OutOfStockException
     */
    public function handle(CheckoutRequest $request): ?string
    {
        $cart = Cart::query()->where(CartSchema::USER_ID, Auth::id())->first();
        $cartItems = $cart?->items;

        if (is_null($cart) || $cartItems->isEmpty()) {
            throw new EmptyCardException;
        }

        $address = $this->userGateway->getAddress(Auth::id(), $request->input('address_id'));
        // A country is required to quote shipping; without this guard a null
        // country_code would surface as a TypeError from the shipping gateway
        if (is_null($address) || is_null($address->country_code)) {
            throw new InvalidAddressException;
        }

        $inventoryGateway = app(InventoryGatewayInterface::class);
        foreach ($cartItems as $cartItem) {
            $variantId = $cartItem->{CartItemSchema::VARIANT_ID};
            $available = $inventoryGateway->availableQuantity((string) $variantId) ?? 0;
            if ($available < $cartItem->{CartItemSchema::QUANTITY}) {
                throw new OutOfStockException(
                    VariantDisplayName::fromSummary(
                        app(CatalogGatewayInterface::class)->getVariantSummaries([(int) $variantId])->get((int) $variantId)
                    ),
                    $available,
                    (int) $variantId,
                );
            }
        }

        $input = new CreateOrderInput;
        $input->cart_items = $cartItems->toArray();
        $input->address = $address;

        // Snapshot the currency the totals are quoted in, so the order keeps
        // it even if the shop default changes later
        $defaultCurrency = app(CoreGatewayInterface::class)->getDefaultCurrency();
        $input->currency_id = is_null($defaultCurrency) ? null : (int) $defaultCurrency->id;

        $shippingMethodId = $request->input('shipping_method_id');
        if (! is_null($shippingMethodId)) {
            $quote = app(ShippingGatewayInterface::class)
                ->getQuote($shippingMethodId, $address->country_code);
            if (is_null($quote)) {
                throw new InvalidShippingMethodException;
            }

            $input->shipping_method_id = $quote->id;
            $input->shipping_method_name = $quote->name;
            $input->shipping_cost = $quote->price;
        }

        // Re-evaluate the cart's stored coupon against the live cart: it may
        // have been deactivated, expired or made limit-exhausted since it was
        // applied, and the cart contents may have changed the computed
        // discount. These input fields are server-authoritative — the HTTP
        // request never carries coupon data.
        $couponCode = $cart->{CartSchema::COUPON_CODE};
        if (! is_null($couponCode)) {
            $evaluation = app(DiscountGatewayInterface::class)
                ->evaluate($couponCode, CartSubtotal::minor($cartItems), (int) Auth::id());

            if (! $evaluation->isEligible()) {
                throw new InvalidCouponException($evaluation->failure);
            }

            $input->coupon_id = $evaluation->discount->coupon_id;
            $input->coupon_code = $evaluation->discount->code;
            $input->discount_minor = $evaluation->discount->discount_minor;
        }

        $orderGateway = app(OrderGatewayInterface::class);

        // One transaction: the gateway's own transaction nests as a savepoint,
        // so a stock-decrement or cart-teardown failure rolls the freshly
        // created order back too instead of leaving a duplicate-order trap
        // for a retry
        try {
            $orderCode = DB::transaction(function () use ($orderGateway, $inventoryGateway, $input, $cart, $cartItems): ?string {
                $orderCode = $orderGateway->createFromCart($input);
                if (! $orderCode) {
                    return null;
                }

                foreach ($cartItems as $cartItem) {
                    if (! $inventoryGateway->decrement(
                        $cartItem->{CartItemSchema::VARIANT_ID},
                        $cartItem->{CartItemSchema::QUANTITY}
                    )) {
                        $variantId = $cartItem->{CartItemSchema::VARIANT_ID};
                        throw new OutOfStockException(
                            VariantDisplayName::fromSummary(
                                app(CatalogGatewayInterface::class)->getVariantSummaries([(int) $variantId])->get((int) $variantId)
                            ),
                            $inventoryGateway->availableQuantity((string) $variantId) ?? 0,
                            (int) $variantId,
                        );
                    }
                }

                $cart->items()->delete();
                $cart->delete();

                return $orderCode;
            });
        } catch (RedemptionRefusedException $e) {
            // The authoritative redemption refused capacity (a concurrent
            // checkout consumed it after the evaluation above); the whole
            // transaction — order included — has already rolled back
            throw new InvalidCouponException(match ($e->reason) {
                RedemptionResult::GLOBAL_LIMIT_REACHED => CouponFailureEnum::USAGE_LIMIT,
                RedemptionResult::PER_USER_LIMIT_REACHED => CouponFailureEnum::PER_USER_LIMIT,
                RedemptionResult::REDEEMED => CouponFailureEnum::USAGE_LIMIT,
            }, previous: $e);
        }

        if (! $orderCode) {
            throw new OrderFailedException;
        }

        event(CartCheckedOutEvent::fill($cart->toArray()));

        return $orderCode;
    }
}
