<?php

namespace Modules\Catalog\Transformers\Admin\AttributeValue;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Catalog\Schemas\AttributeValue\AttributeValueSchema;
use Modules\Catalog\Transformers\Admin\AttributeValue\AttributeValueTranslationResource as AVTResource;

class AttributeValueResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            AttributeValueSchema::ID => $this->{AttributeValueSchema::ID},
            AttributeValueSchema::ATTRIBUTE_ID => $this->{AttributeValueSchema::ATTRIBUTE_ID},
            AttributeValueSchema::POSITION => $this->{AttributeValueSchema::POSITION},
            AttributeValueSchema::CREATED_AT => $this->{AttributeValueSchema::CREATED_AT},
            AttributeValueSchema::UPDATED_AT => $this->{AttributeValueSchema::UPDATED_AT},
            AttributeValueSchema::RES_TRANSLATIONS => AVTResource::collection($this->whenLoaded(AttributeValueSchema::RES_TRANSLATIONS)),
        ];
    }
}
