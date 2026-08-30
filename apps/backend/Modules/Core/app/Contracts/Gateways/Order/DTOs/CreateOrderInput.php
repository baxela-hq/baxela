<?php

namespace Modules\Core\Contracts\Gateways\Order\DTOs;

use Modules\Core\Contracts\Gateways\User\DTOs\AddressDto;

class CreateOrderInput
{
    /**
     * @var array<int, array{variant_id: int, price_snapshot: string, product_name_snapshot: string, quantity: int}>
     */
    public array $cart_items = [];

    public ?AddressDto $address = null;

    public ?int $shipping_method_id = null;

    public ?string $shipping_method_name = null;

    public float $shipping_cost = 0.0;
}
