<?php

namespace Modules\Shipping\Transformers\User\Shipment;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Shipping\Schemas\Shipment\ShipmentSchema;

class ShipmentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            ShipmentSchema::ID => $this->{ShipmentSchema::ID},
            ShipmentSchema::ORDER_ID => $this->{ShipmentSchema::ORDER_ID},
            ShipmentSchema::CARRIER_NAME => $this->{ShipmentSchema::CARRIER_NAME},
            ShipmentSchema::TRACKING_NUMBER => $this->{ShipmentSchema::TRACKING_NUMBER},
            ShipmentSchema::TRACKING_URL => $this->{ShipmentSchema::TRACKING_URL},
            ShipmentSchema::STATUS => $this->{ShipmentSchema::STATUS},
            ShipmentSchema::SHIPPED_AT => $this->{ShipmentSchema::SHIPPED_AT},
            ShipmentSchema::DELIVERED_AT => $this->{ShipmentSchema::DELIVERED_AT},
            ShipmentSchema::CREATED_AT => $this->{ShipmentSchema::CREATED_AT},
            ShipmentSchema::UPDATED_AT => $this->{ShipmentSchema::UPDATED_AT},
        ];
    }
}
