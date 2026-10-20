<?php

namespace Modules\Cart\Actions\User\Tax;

use Modules\Cart\Exceptions\User\Checkout\InvalidAddressException;
use Modules\Cart\Http\Requests\User\Tax\TaxPreviewRequest;
use Modules\Cart\Models\Cart;
use Modules\Cart\Schemas\Cart\CartSchema;
use Modules\Cart\Schemas\CartItem\CartItemSchema;
use Modules\Cart\Support\CartSubtotal;
use Modules\Cart\Support\RefreshesCartPrices;
use Modules\Core\Contracts\Gateways\Core\CoreGatewayInterface;
use Modules\Core\Contracts\Gateways\Discount\DiscountGatewayInterface;
use Modules\Core\Contracts\Gateways\Shipping\ShippingGatewayInterface;
use Modules\Core\Contracts\Gateways\Tax\DTOs\TaxCalculationInput;
use Modules\Core\Contracts\Gateways\Tax\DTOs\TaxCalculationItem;
use Modules\Core\Contracts\Gateways\Tax\DTOs\TaxResult;
use Modules\Core\Contracts\Gateways\Tax\TaxGatewayInterface;
use Modules\Core\Contracts\Gateways\User\UserGatewayInterface;
use Modules\Core\Support\Money;
use Modules\Core\Utils\Auth;

class ShowTaxPreviewAction
{
    public function __construct(
        protected UserGatewayInterface $userGateway,
        protected RefreshesCartPrices $refreshesCartPrices,
    ) {}

    /**
     * Advisory tax figure for the checkout summary, computed by the SAME
     * calculator the order pipeline charges with. The order stays
     * authoritative — prices, quotes and cart contents may change between
     * preview and checkout.
     *
     * @return array{result: TaxResult, currency_id: ?int, shipping_tax_included: bool}
     *
     * @throws InvalidAddressException
     */
    public function handle(TaxPreviewRequest $request): array
    {
        $cart = Cart::query()->where(CartSchema::USER_ID, Auth::id())->first();
        $cartItems = $cart?->items;

        $defaultCurrency = app(CoreGatewayInterface::class)->getDefaultCurrency();

        // An empty cart has nothing to rate — a zero preview beats an error
        // for a summary panel
        if (is_null($cart) || $cartItems->isEmpty()) {
            return [
                'result' => new TaxResult,
                'currency_id' => is_null($defaultCurrency) ? null : (int) $defaultCurrency->id,
                'shipping_tax_included' => false,
            ];
        }

        $address = $this->userGateway->getAddress(Auth::id(), $request->input('address_id'));
        if (is_null($address) || is_null($address->country_code)) {
            throw new InvalidAddressException;
        }

        // Write-through refresh keeps the bases identical to what checkout
        // would charge a moment later
        $this->refreshesCartPrices->refresh($cartItems);

        $shippingCostMinor = 0;
        $shippingIncluded = false;

        $shippingMethodId = $request->input('shipping_method_id');
        if (! is_null($shippingMethodId)) {
            $quote = app(ShippingGatewayInterface::class)
                ->getQuote($shippingMethodId, $address->country_code);

            // A method unavailable for the address simply contributes no
            // shipping tax — checkout is the one that refuses it
            if (! is_null($quote)) {
                $shippingCostMinor = Money::fromDecimal(number_format($quote->price, 2, '.', ''));
                $shippingIncluded = true;
            }
        }

        // Coupon mirrors checkout's re-evaluation; an ineligible coupon
        // contributes no discount to the tax base here (checkout refuses
        // it properly when the order is placed)
        $couponDiscountMinor = 0;
        $couponCode = $cart->{CartSchema::COUPON_CODE};
        if (! is_null($couponCode)) {
            $evaluation = app(DiscountGatewayInterface::class)
                ->evaluate($couponCode, CartSubtotal::minor($cartItems), (int) Auth::id());

            if ($evaluation->isEligible()) {
                $couponDiscountMinor = $evaluation->discount->discount_minor;
            }
        }

        $taxItems = $cartItems->map(fn ($item): TaxCalculationItem => new TaxCalculationItem(
            variant_id: (int) $item->{CartItemSchema::VARIANT_ID},
            amount_minor: Money::fromDecimal((string) $item->{CartItemSchema::PRICE_SNAPSHOT})
                * (int) $item->{CartItemSchema::QUANTITY},
            tax_class_id: $item->getAttribute(CartItemSchema::ATTR_VARIANT_SUMMARY)?->tax_class_id,
        ))->all();

        $result = app(TaxGatewayInterface::class)->calculateForItems(new TaxCalculationInput(
            items: $taxItems,
            country_code: (string) $address->country_code,
            coupon_discount_minor: $couponDiscountMinor,
            shipping_cost_minor: $shippingCostMinor,
        ));

        return [
            'result' => $result,
            'currency_id' => is_null($defaultCurrency) ? null : (int) $defaultCurrency->id,
            'shipping_tax_included' => $shippingIncluded,
        ];
    }
}
