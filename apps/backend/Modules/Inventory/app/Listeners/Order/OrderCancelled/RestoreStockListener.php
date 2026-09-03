<?php

namespace Modules\Inventory\Listeners\Order\OrderCancelled;

use Modules\Core\Contracts\Events\Order\OrderCancelledEvent;
use Modules\Core\Contracts\Gateways\Inventory\InventoryGatewayInterface;
use Modules\Core\Contracts\Gateways\Order\OrderGatewayInterface;

/**
 * Returns reserved stock when an order is cancelled (admin action or the
 * order:cancel-expired schedule). Stock was taken at checkout, so any
 * non-paid cancelled order hands its quantity back.
 */
class RestoreStockListener
{
    public function __construct(
        private readonly InventoryGatewayInterface $inventoryGateway,
        private readonly OrderGatewayInterface $orderGateway,
    ) {}

    public function handle(OrderCancelledEvent $event): void
    {
        $items = $this->orderGateway->findOrderItems($event->id);

        foreach ($items as $item) {
            $this->inventoryGateway->restore(
                (string) $item['variant_id'],
                $item['quantity'],
            );
        }
    }
}
