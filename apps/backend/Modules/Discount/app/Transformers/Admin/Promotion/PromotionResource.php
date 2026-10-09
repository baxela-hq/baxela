<?php

namespace Modules\Discount\Transformers\Admin\Promotion;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Discount\Models\Promotion;
use Modules\Discount\Schemas\Promotion\PromotionSchema;

/**
 * @mixin Promotion
 */
class PromotionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * The scope selections come from the batched attributes the list
     * action attaches; single-record contexts fall back to the pivot
     * lookups.
     */
    public function toArray(Request $request): array
    {
        return [
            PromotionSchema::ID => $this->{PromotionSchema::ID},
            PromotionSchema::NAME => $this->{PromotionSchema::NAME},
            PromotionSchema::SCOPE => $this->{PromotionSchema::SCOPE},
            PromotionSchema::TYPE => $this->{PromotionSchema::TYPE},
            PromotionSchema::VALUE => $this->{PromotionSchema::VALUE},
            PromotionSchema::STARTS_AT => $this->{PromotionSchema::STARTS_AT},
            PromotionSchema::ENDS_AT => $this->{PromotionSchema::ENDS_AT},
            PromotionSchema::PRIORITY => $this->{PromotionSchema::PRIORITY},
            PromotionSchema::IS_ACTIVE => $this->{PromotionSchema::IS_ACTIVE},
            PromotionSchema::PRODUCT_IDS => $this->resource->getAttribute(PromotionSchema::ATTR_PRODUCT_IDS)
                ?? $this->selectedProductIds()->sort()->values()->all(),
            PromotionSchema::CATEGORY_IDS => $this->resource->getAttribute(PromotionSchema::ATTR_CATEGORY_IDS)
                ?? $this->selectedCategoryIds()->sort()->values()->all(),
            PromotionSchema::CREATED_AT => $this->{PromotionSchema::CREATED_AT},
            PromotionSchema::UPDATED_AT => $this->{PromotionSchema::UPDATED_AT},
        ];
    }
}
