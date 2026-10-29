<?php

namespace Modules\Core\Contracts\Gateways\Order\DTOs;

use Modules\Core\Contracts\Gateways\User\DTOs\AddressDto;

class CreateOrderInput
{
    /**
     * @var array<int, array{variant_id: int, price_snapshot: string, product_name_snapshot: string, quantity: int}>
     */
    public array $cart_items = [];

    /**
     * Promotion facts per order item, keyed by variant id (a cart holds at
     * most one line per variant). Server-populated by CheckoutAction from
     * the same in-transaction pricing resolution that refreshed the
     * cart-item snapshots — never client input. Absent entry = the item
     * was not promoted.
     *
     * @var array<int, OrderItemPromotion>
     */
    public array $item_promotions = [];

    public ?AddressDto $address = null;

    public ?int $shipping_method_id = null;

    public ?string $shipping_method_name = null;

    public float $shipping_cost = 0.0;

    public ?int $currency_id = null;

    /**
     * Display currency the customer had selected at checkout (non-base
     * only). Server-resolved from the request's display_currency code
     * against active currencies — the rate is never trusted from the
     * client. Null when the customer used the base currency.
     */
    public ?string $display_currency_code = null;

    public ?string $display_fx_rate = null;

    /**
     * Coupon applied at checkout. Server-populated internal values only —
     * never client input. CheckoutAction is the sole writer, after
     * re-evaluating the cart's stored coupon code against the live cart.
     */
    public ?int $coupon_id = null;

    public ?string $coupon_code = null;

    public int $discount_minor = 0;

    /**
     * Tax facts resolved by the Tax gateway at checkout — server-populated
     * only, like every other money field here. tax_amount_minor INCLUDES
     * the shipping tax; the gateway adds it to the total exactly once.
     *
     * @var array<int, OrderItemTax> keyed by variant id
     */
    public int $tax_amount_minor = 0;

    public int $shipping_tax_minor = 0;

    public ?string $shipping_tax_rate = null;

    public ?int $shipping_tax_class_id = null;

    public array $item_taxes = [];
}
