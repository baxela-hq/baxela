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
use Modules\Cart\Support\RefreshesCartPrices;
use Modules\Cart\Support\VariantDisplayName;
use Modules\Core\Contracts\Events\Cart\CartCheckedOutEvent;
use Modules\Core\Contracts\Gateways\Catalog\CatalogGatewayInterface;
use Modules\Core\Contracts\Gateways\Core\CoreGatewayInterface;
use Modules\Core\Contracts\Gateways\Discount\CouponFailureEnum;
use Modules\Core\Contracts\Gateways\Discount\DiscountGatewayInterface;
use Modules\Core\Contracts\Gateways\Discount\RedemptionResult;
use Modules\Core\Contracts\Gateways\Inventory\InventoryGatewayInterface;
use Modules\Core\Contracts\Gateways\Order\DTOs\CreateOrderInput;
use Modules\Core\Contracts\Gateways\Order\DTOs\OrderItemPromotion;
use Modules\Core\Contracts\Gateways\Order\OrderGatewayInterface;
use Modules\Core\Contracts\Gateways\Shipping\ShippingGatewayInterface;
use Modules\Core\Contracts\Gateways\User\UserGatewayInterface;
use Modules\Core\Exceptions\Discount\RedemptionRefusedException;
use Modules\Core\Support\Money;
use Modules\Core\Utils\Auth;
use RuntimeException;

class CheckoutAction
{
    public function __construct(
        protected UserGatewayInterface $userGateway,
        protected RefreshesCartPrices $refreshesCartPrices,
    ) {}

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

        $orderGateway = app(OrderGatewayInterface::class);

        // One transaction: the gateway's own transaction nests as a savepoint,
        // so a stock-decrement or cart-teardown failure rolls the freshly
        // created order back too instead of leaving a duplicate-order trap
        // for a retry
        //
        // Pricing consistency guarantee: the locked lines are re-priced ONCE
        // in-memory, and that single resolution feeds everything charged —
        // the refreshed snapshots, the coupon's subtotal anchor and the
        // order-item promotion facts — so the order's totals equal the sum
        // of its item snapshots by construction. Promotion rows are read
        // unlocked: a concurrent admin edit may interleave, but one
        // checkout is always internally coherent.
        try {
            $orderCode = DB::transaction(function () use ($orderGateway, $inventoryGateway, $input, $cart): ?string {
                $cart = Cart::query()
                    ->where(CartSchema::USER_ID, Auth::id())
                    ->lockForUpdate()
                    ->first();

                $cartItems = $cart?->items()->lockForUpdate()->get();
                if (is_null($cart) || $cartItems->isEmpty()) {
                    throw new EmptyCardException;
                }

                // Current effective prices (promotions included) for every
                // line — snapshots updated in place, summaries attached
                $this->refreshesCartPrices->refresh($cartItems);

                $input->cart_items = $cartItems->map(fn ($item): array => [
                    'variant_id' => (int) $item->{CartItemSchema::VARIANT_ID},
                    'price_snapshot' => (string) $item->{CartItemSchema::PRICE_SNAPSHOT},
                    'product_name_snapshot' => (string) $item->{CartItemSchema::PRODUCT_NAME_SNAPSHOT},
                    'quantity' => (int) $item->{CartItemSchema::QUANTITY},
                ])->all();

                // Promotion facts per line, from the same resolution — the
                // order items snapshot what was actually taken off
                foreach ($cartItems as $item) {
                    $summary = $item->getAttribute(CartItemSchema::ATTR_VARIANT_SUMMARY);
                    $promotion = $summary?->promotion;
                    if ($promotion === null || $summary->compare_price === null) {
                        continue;
                    }

                    $promoted = $promotion->applyTo($summary->compare_price);
                    $input->item_promotions[(int) $item->{CartItemSchema::VARIANT_ID}] = new OrderItemPromotion(
                        variant_id: (int) $item->{CartItemSchema::VARIANT_ID},
                        base_price: (string) $summary->compare_price,
                        promotion_discount: Money::toDecimal($promoted->discount_minor),
                        promotion_id: $promotion->promotion_id,
                    );
                }

                // Re-evaluate the cart's stored coupon against the freshly
                // priced cart: it may have been deactivated, expired or made
                // limit-exhausted since it was applied, and the promotion
                // refresh may have changed the subtotal the discount
                // anchors to. These input fields are server-authoritative —
                // the HTTP request never carries coupon data.
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
            // createFromCart refused without throwing and nothing else
            // records why — report before the safe envelope swallows it.
            report(new RuntimeException('order gateway returned no order code'));

            throw new OrderFailedException;
        }

        event(CartCheckedOutEvent::fill($cart->toArray()));

        return $orderCode;
    }
}
