<?php

namespace Modules\Shipping\Actions\Admin\Shipment;

use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\Events\Shipping\ShipmentCreatedEvent;
use Modules\Core\Contracts\Gateways\Order\OrderGatewayInterface;
use Modules\Shipping\Exceptions\Shipment\AlreadyExistsException;
use Modules\Shipping\Exceptions\Shipment\CreationFailedException;
use Modules\Shipping\Exceptions\Shipment\OrderNotFoundException;
use Modules\Shipping\Models\Shipment;
use Modules\Shipping\Schemas\Shipment\ShipmentSchema;
use Modules\Shipping\Schemas\Shipment\ShipmentStatusEnum;
use Throwable;

class CreateShipmentAction
{
    public function __construct(protected OrderGatewayInterface $orderGateway) {}

    /**
     * @throws OrderNotFoundException|AlreadyExistsException|CreationFailedException|Throwable
     */
    public function handle(array $data): Shipment
    {
        $orderId = $data[ShipmentSchema::ORDER_ID];

        if (is_null($this->orderGateway->findOrder($orderId))) {
            throw new OrderNotFoundException;
        }

        $exists = Shipment::query()->where(ShipmentSchema::ORDER_ID, $orderId)->exists();
        if ($exists) {
            throw new AlreadyExistsException;
        }

        try {
            DB::beginTransaction();

            $record = Shipment::query()->create([
                ShipmentSchema::ORDER_ID => $orderId,
                ShipmentSchema::CARRIER_NAME => $data[ShipmentSchema::CARRIER_NAME],
                ShipmentSchema::TRACKING_NUMBER => $data[ShipmentSchema::TRACKING_NUMBER],
                ShipmentSchema::TRACKING_URL => $data[ShipmentSchema::TRACKING_URL],
                ShipmentSchema::NOTES => $data[ShipmentSchema::NOTES],
                ShipmentSchema::STATUS => ShipmentStatusEnum::PENDING,
            ]);

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            report($e);
            throw new CreationFailedException;
        }

        event(ShipmentCreatedEvent::fill($record->toArray()));

        return $record;
    }
}
