<?php

namespace Modules\Cart\Transformers\User\CartItem;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Cart\Models\CartItem;
use Modules\Cart\Schemas\CartItem\CartItemSchema;

/**
 * @mixin CartItem
 */
class CartItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * Variant display fields (label, product link) come from the
     * VariantSummary the list action attached; they are null when the
     * variant no longer resolves — the snapshots still identify the item.
     */
    public function toArray(Request $request): array
    {
        $summary = $this->resource->getAttribute(CartItemSchema::ATTR_VARIANT_SUMMARY);

        return [
            CartItemSchema::ID => $this->resource->{CartItemSchema::ID},
            CartItemSchema::VARIANT_ID => $this->resource->{CartItemSchema::VARIANT_ID},
            CartItemSchema::CART_ID => $this->resource->{CartItemSchema::CART_ID},
            CartItemSchema::PRICE_SNAPSHOT => $this->resource->{CartItemSchema::PRICE_SNAPSHOT},
            CartItemSchema::PRODUCT_NAME_SNAPSHOT => $this->resource->{CartItemSchema::PRODUCT_NAME_SNAPSHOT},
            CartItemSchema::VARIANT_LABEL => $summary?->variant_label,
            CartItemSchema::PRODUCT_ID => $summary?->product_id,
            CartItemSchema::PRODUCT_SLUG => $summary?->product_slug,
            CartItemSchema::QUANTITY => $this->resource->{CartItemSchema::QUANTITY},
            CartItemSchema::CREATED_AT => $this->resource->{CartItemSchema::CREATED_AT},
            CartItemSchema::UPDATED_AT => $this->resource->{CartItemSchema::UPDATED_AT},
        ];
    }
}
