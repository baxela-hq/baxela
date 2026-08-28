<?php

namespace Modules\Order\Transformers\Admin\Order;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Order\Schemas\Order\OrderSchema;
use Modules\Order\Transformers\Admin\OrderAddress\OrderAddressResource;
use Modules\Order\Transformers\Admin\OrderItem\OrderItemResource;

class OrderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            OrderSchema::ID => $this->resource->{OrderSchema::ID},
            OrderSchema::USER_ID => $this->resource->{OrderSchema::USER_ID},
            OrderSchema::STATUS => $this->resource->{OrderSchema::STATUS},
            OrderSchema::TOTAL_AMOUNT => $this->resource->{OrderSchema::TOTAL_AMOUNT},
            OrderSchema::CREATED_AT => $this->resource->{OrderSchema::CREATED_AT},
            OrderSchema::UPDATED_AT => $this->resource->{OrderSchema::UPDATED_AT},
            OrderSchema::RES_ITEMS => OrderItemResource::collection($this->whenLoaded(OrderSchema::RES_ITEMS)),
            OrderSchema::RES_ADDRESSES => OrderAddressResource::collection($this->whenLoaded(OrderSchema::RES_ADDRESSES)),
        ];
    }
}
