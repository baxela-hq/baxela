<?php

namespace Modules\Shipping\Actions\Admin\Shipment;

use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\Events\Shipping\ShipmentDeliveredEvent;
use Modules\Core\Contracts\Events\Shipping\ShipmentShippedEvent;
use Modules\Core\Contracts\Gateways\Order\OrderGatewayInterface;
use Modules\Shipping\Exceptions\Shipment\InvalidTransitionException;
use Modules\Shipping\Exceptions\Shipment\UpdateFailedException;
use Modules\Shipping\Models\Shipment;
use Modules\Shipping\Schemas\Shipment\ShipmentSchema;
use Modules\Shipping\Schemas\Shipment\ShipmentStatusEnum;
use Throwable;

class UpdateShipmentAction
{
    public function __construct(protected OrderGatewayInterface $orderGateway) {}

    /**
     * @throws InvalidTransitionException|UpdateFailedException|Throwable
     */
    public function handle(string $id, array $data): Shipment
    {
        $record = Shipment::query()->findOrFail($id);

        $previous = $record->{ShipmentSchema::STATUS};
        $status = ShipmentStatusEnum::from($data[ShipmentSchema::STATUS] ?? $previous->value);

        if (! $previous->canTransitionTo($status)) {
            throw new InvalidTransitionException;
        }

        try {
            DB::beginTransaction();

            $body = [
                ShipmentSchema::ORDER_ID => $data[ShipmentSchema::ORDER_ID],
                ShipmentSchema::CARRIER_NAME => $data[ShipmentSchema::CARRIER_NAME],
                ShipmentSchema::TRACKING_NUMBER => $data[ShipmentSchema::TRACKING_NUMBER],
                ShipmentSchema::TRACKING_URL => $data[ShipmentSchema::TRACKING_URL],
                ShipmentSchema::NOTES => $data[ShipmentSchema::NOTES],
                ShipmentSchema::STATUS => $status,
            ];

            if ($status === ShipmentStatusEnum::SHIPPED && is_null($record->{ShipmentSchema::SHIPPED_AT})) {
                $body[ShipmentSchema::SHIPPED_AT] = now();
            }

            if ($status === ShipmentStatusEnum::DELIVERED && is_null($record->{ShipmentSchema::DELIVERED_AT})) {
                $body[ShipmentSchema::DELIVERED_AT] = now();
            }

            $record->update($body);

            if ($status !== $previous) {
                $this->syncOrderStatus($record, $previous, $status);
            }

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            report($e);
            throw new UpdateFailedException;
        }

        $this->dispatchTransitionEvent($record, $previous, $status);

        return $record->refresh();
    }

    private function syncOrderStatus(Shipment $record, ShipmentStatusEnum $previous, ShipmentStatusEnum $status): void
    {
        if ($status === ShipmentStatusEnum::SHIPPED && $previous !== ShipmentStatusEnum::SHIPPED) {
            if (! $this->orderGateway->markAsShipped($record->{ShipmentSchema::ORDER_ID})) {
                throw new \RuntimeException('markAsShipped failed for order '.$record->{ShipmentSchema::ORDER_ID});
            }
        }

        if ($status === ShipmentStatusEnum::DELIVERED && $previous !== ShipmentStatusEnum::DELIVERED) {
            if (! $this->orderGateway->markAsDelivered($record->{ShipmentSchema::ORDER_ID})) {
                throw new \RuntimeException('markAsDelivered failed for order '.$record->{ShipmentSchema::ORDER_ID});
            }
        }
    }

    private function dispatchTransitionEvent(Shipment $record, ShipmentStatusEnum $previous, ShipmentStatusEnum $status): void
    {
        if ($status === $previous) {
            return;
        }

        $event = match (true) {
            $status === ShipmentStatusEnum::SHIPPED => ShipmentShippedEvent::class,
            $status === ShipmentStatusEnum::DELIVERED => ShipmentDeliveredEvent::class,
            default => null,
        };

        if (! is_null($event)) {
            event($event::fill($record->toArray()));
        }
    }
}
