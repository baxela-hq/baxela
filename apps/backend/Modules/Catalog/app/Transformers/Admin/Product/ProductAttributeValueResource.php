<?php

namespace Modules\Catalog\Transformers\Admin\Product;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Catalog\Schemas\Product\ProductAttributeValueSchema;
use Modules\Catalog\Transformers\Admin\Attribute\AttributeResource;
use Modules\Catalog\Transformers\Admin\AttributeValue\AttributeValueResource;

class ProductAttributeValueResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            ProductAttributeValueSchema::ID => $this->{ProductAttributeValueSchema::ID},
            ProductAttributeValueSchema::ATTRIBUTE_ID => $this->{ProductAttributeValueSchema::ATTRIBUTE_ID},
            ProductAttributeValueSchema::ATTRIBUTE_VALUE_ID => $this->{ProductAttributeValueSchema::ATTRIBUTE_VALUE_ID},
            ProductAttributeValueSchema::TEXT_VALUE => $this->{ProductAttributeValueSchema::TEXT_VALUE},
            ProductAttributeValueSchema::NUMBER_VALUE => $this->{ProductAttributeValueSchema::NUMBER_VALUE},
            ProductAttributeValueSchema::BOOLEAN_VALUE => $this->{ProductAttributeValueSchema::BOOLEAN_VALUE},
            ProductAttributeValueSchema::RES_ATTRIBUTE => new AttributeResource($this->whenLoaded(ProductAttributeValueSchema::RES_ATTRIBUTE)),
            ProductAttributeValueSchema::RES_ATTRIBUTE_VALUE => new AttributeValueResource($this->whenLoaded(ProductAttributeValueSchema::RES_ATTRIBUTE_VALUE)),
        ];
    }
}
