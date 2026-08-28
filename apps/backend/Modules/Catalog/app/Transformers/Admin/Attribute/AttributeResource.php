<?php

namespace Modules\Catalog\Transformers\Admin\Attribute;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Catalog\Schemas\Attribute\AttributeSchema;
use Modules\Catalog\Transformers\Admin\AttributeGroup\AttributeGroupResource;
use Modules\Catalog\Transformers\Admin\AttributeValue\AttributeValueResource;

class AttributeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            AttributeSchema::ID => $this->{AttributeSchema::ID},
            AttributeSchema::GROUP_ID => $this->{AttributeSchema::GROUP_ID},
            AttributeSchema::CODE => $this->{AttributeSchema::CODE},
            AttributeSchema::DATA_TYPE => $this->{AttributeSchema::DATA_TYPE},
            AttributeSchema::IS_FILTERABLE => $this->{AttributeSchema::IS_FILTERABLE},
            AttributeSchema::POSITION => $this->{AttributeSchema::POSITION},
            AttributeSchema::CREATED_AT => $this->{AttributeSchema::CREATED_AT},
            AttributeSchema::UPDATED_AT => $this->{AttributeSchema::UPDATED_AT},
            AttributeSchema::RES_TRANSLATIONS => AttributeTranslationResource::collection($this->whenLoaded(AttributeSchema::RES_TRANSLATIONS)),
            AttributeSchema::RES_VALUES => AttributeValueResource::collection($this->whenLoaded(AttributeSchema::RES_VALUES)),
            AttributeSchema::RES_VALUES_COUNT => $this->{AttributeSchema::RES_VALUES_COUNT},
            AttributeSchema::RES_GROUP => new AttributeGroupResource($this->whenLoaded(AttributeSchema::RES_GROUP)),
        ];
    }
}
