<?php

namespace Modules\Cart\Transformers\User\CartItem;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Cart\Schemas\CartItem\CartItemSchema;

class CartItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            CartItemSchema::ID => $this->resource->{CartItemSchema::ID},
            CartItemSchema::VARIANT_ID => $this->resource->{CartItemSchema::VARIANT_ID},
            CartItemSchema::CART_ID => $this->resource->{CartItemSchema::CART_ID},
            CartItemSchema::PRICE_SNAPSHOT => $this->resource->{CartItemSchema::PRICE_SNAPSHOT},
            CartItemSchema::PRODUCT_NAME_SNAPSHOT => $this->resource->{CartItemSchema::PRODUCT_NAME_SNAPSHOT},
            CartItemSchema::QUANTITY => $this->resource->{CartItemSchema::QUANTITY},
            CartItemSchema::CREATED_AT => $this->resource->{CartItemSchema::CREATED_AT},
            CartItemSchema::UPDATED_AT => $this->resource->{CartItemSchema::UPDATED_AT},
        ];
    }
}
