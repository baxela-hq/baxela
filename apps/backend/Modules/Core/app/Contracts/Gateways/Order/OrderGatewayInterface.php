<?php

namespace Modules\Core\Contracts\Gateways\Order;

use Modules\Order\Gateways\DTOs\GetOrderOutput;

interface OrderGatewayInterface
{
    /**
     * @param  array{variant_id:int, price_snapshot:string, product_name_snapshot:string, quantity:int}  $cartItems
     * @return null|int OrderId
     */
    public function createFromCart(array $cartItems): ?int;

    public function getOrder(string $orderId, string $userId): ?GetOrderOutput;
}
