<?php

namespace Modules\Order\Transformers\User\Order;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Order\Schemas\Order\OrderSchema;
use Modules\Order\Transformers\User\OrderAddress\OrderAddressResource;

class OrderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            OrderSchema::ID => $this->resource->{OrderSchema::ID},
            OrderSchema::STATUS => $this->resource->{OrderSchema::STATUS},
            OrderSchema::TOTAL_AMOUNT => $this->resource->{OrderSchema::TOTAL_AMOUNT},
            OrderSchema::SHIPPING_METHOD_NAME => $this->resource->{OrderSchema::SHIPPING_METHOD_NAME},
            OrderSchema::SHIPPING_COST => $this->resource->{OrderSchema::SHIPPING_COST},
            OrderSchema::RES_ADDRESSES => OrderAddressResource::collection($this->whenLoaded(OrderSchema::RES_ADDRESSES)),
        ];
    }
}
