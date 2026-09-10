<?php

namespace Modules\Core\Contracts\Gateways\Order;

use Modules\Core\Contracts\Gateways\Order\DTOs\CreateOrderInput;
use Modules\Order\Gateways\DTOs\GetOrderOutput;

interface OrderGatewayInterface
{
    /**
     * @param  CreateOrderInput  $input  cart items, address snapshot, shipping choice
     * @return null|string The new order's customer-facing code
     */
    public function createFromCart(CreateOrderInput $input): ?string;

    public function getOrder(string $orderCode, string $userId): ?GetOrderOutput;

    public function findOrder(int $orderId): ?GetOrderOutput;

    public function findOrderByCode(string $code): ?GetOrderOutput;

    /**
     * @return array<int, array{variant_id: int, quantity: int}>
     */
    public function findOrderItems(int $orderId): array;

    public function markAsPaid(int $orderId): bool;

    public function markAsShipped(int $orderId): bool;

    public function markAsDelivered(int $orderId): bool;
}
