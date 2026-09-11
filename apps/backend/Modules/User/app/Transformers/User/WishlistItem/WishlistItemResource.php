<?php

namespace Modules\User\Transformers\User\WishlistItem;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Core\Contracts\Gateways\Catalog\DTOs\ProductSummary;
use Modules\User\Models\WishlistItem;
use Modules\User\Schemas\WishlistItem\WishlistItemSchema;

/**
 * @mixin WishlistItem
 */
class WishlistItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        $summary = $this->resource->getAttribute(WishlistItemSchema::ATTR_PRODUCT_SUMMARY);

        return [
            WishlistItemSchema::ID => $this->resource->{WishlistItemSchema::ID},
            WishlistItemSchema::PRODUCT_ID => $this->resource->{WishlistItemSchema::PRODUCT_ID},
            // Null when the product is no longer available — the row
            // survives so the UI can offer removal.
            'product' => $summary instanceof ProductSummary ? [
                'id' => $summary->id,
                'title' => $summary->title,
                'slug' => $summary->slug,
                'price' => $summary->price,
                'compare_price' => $summary->compare_price,
                'image_url' => $summary->image_url,
            ] : null,
            WishlistItemSchema::CREATED_AT => $this->resource->{WishlistItemSchema::CREATED_AT},
        ];
    }
}
