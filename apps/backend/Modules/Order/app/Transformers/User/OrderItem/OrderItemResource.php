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
        $summary = $this->resource->getAttribute(OrderItemSchema::ATTR_VARIANT_SUMMARY);

        return [
            OrderItemSchema::VARIANT_ID => $this->resource->{OrderItemSchema::VARIANT_ID},
            OrderItemSchema::PRODUCT_NAME_SNAPSHOT => $this->resource->{OrderItemSchema::PRODUCT_NAME_SNAPSHOT},
            OrderItemSchema::PRODUCT_SLUG_SNAPSHOT => $this->resource->{OrderItemSchema::PRODUCT_SLUG_SNAPSHOT},
            OrderItemSchema::PRICE_SNAPSHOT => $this->resource->{OrderItemSchema::PRICE_SNAPSHOT},
            OrderItemSchema::QUANTITY => $this->resource->{OrderItemSchema::QUANTITY},
            // Current product image resolved through the Catalog gateway;
            // null when the variant no longer resolves — the name snapshot
            // still identifies the row.
            OrderItemSchema::IMAGE_URL => $summary?->image_url,
        ];
    }
}
