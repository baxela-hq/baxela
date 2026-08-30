<?php

namespace Modules\Shipping\Actions\User\Shipment;

use Modules\Core\Contracts\Gateways\Order\OrderGatewayInterface;
use Modules\Core\Utils\Auth;
use Modules\Shipping\Exceptions\Shipment\NotFoundException;
use Modules\Shipping\Models\Shipment;
use Modules\Shipping\Schemas\Shipment\ShipmentSchema;

class ShowShipmentAction
{
    public function __construct(protected OrderGatewayInterface $orderGateway) {}

    /**
     * @throws NotFoundException|Throwable
     */
    public function handle(string $orderId): Shipment
    {
        $order = $this->orderGateway->findOrder((int) $orderId);

        if (is_null($order) || (int) $order->user_id !== (int) Auth::id()) {
            throw new NotFoundException;
        }

        return Shipment::query()
            ->where(ShipmentSchema::ORDER_ID, (int) $orderId)
            ->firstOrFail();
    }
}
