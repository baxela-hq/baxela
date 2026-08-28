<?php

namespace Modules\Order\Transformers\User\OrderItem;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Order\Schemas\OrderItem\OrderItemSchema;

class OrderItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            OrderItemSchema::ID => $this->resource->{OrderItemSchema::ID},
            OrderItemSchema::VARIANT_ID => $this->resource->{OrderItemSchema::VARIANT_ID},
            OrderItemSchema::PRODUCT_NAME_SNAPSHOT => $this->resource->{OrderItemSchema::PRODUCT_NAME_SNAPSHOT},
            OrderItemSchema::PRICE_SNAPSHOT => $this->resource->{OrderItemSchema::PRICE_SNAPSHOT},
            OrderItemSchema::QUANTITY => $this->resource->{OrderItemSchema::QUANTITY},
        ];
    }
}
