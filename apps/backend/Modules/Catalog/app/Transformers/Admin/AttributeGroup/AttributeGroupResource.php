<?php

namespace Modules\Catalog\Transformers\Admin\AttributeGroup;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Catalog\Schemas\AttributeGroup\AttributeGroupSchema;
use Modules\Catalog\Transformers\Admin\Attribute\AttributeResource;

class AttributeGroupResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            AttributeGroupSchema::ID => $this->{AttributeGroupSchema::ID},
            AttributeGroupSchema::POSITION => $this->{AttributeGroupSchema::POSITION},
            AttributeGroupSchema::CREATED_AT => $this->{AttributeGroupSchema::CREATED_AT},
            AttributeGroupSchema::UPDATED_AT => $this->{AttributeGroupSchema::UPDATED_AT},
            AttributeGroupSchema::RES_TRANSLATIONS => AttributeGroupTranslationResource::collection($this->whenLoaded(AttributeGroupSchema::RES_TRANSLATIONS)),
            AttributeGroupSchema::RES_ATTRIBUTES => AttributeResource::collection($this->whenLoaded(AttributeGroupSchema::RES_ATTRIBUTES)),
            AttributeGroupSchema::RES_ATTRIBUTES_COUNT => $this->{AttributeGroupSchema::RES_ATTRIBUTES_COUNT},
        ];
    }
}
