<?php

namespace Modules\Catalog\Transformers\Admin\AttributeTemplate;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Catalog\Schemas\AttributeTemplate\AttributeTemplateSchema;
use Modules\Catalog\Transformers\Admin\AttributeGroup\AttributeGroupResource;

class AttributeTemplateResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            AttributeTemplateSchema::ID => $this->{AttributeTemplateSchema::ID},
            AttributeTemplateSchema::TITLE => $this->{AttributeTemplateSchema::TITLE},
            AttributeTemplateSchema::DESCRIPTION => $this->{AttributeTemplateSchema::DESCRIPTION},
            AttributeTemplateSchema::IS_ACTIVE => $this->{AttributeTemplateSchema::IS_ACTIVE},
            AttributeTemplateSchema::POSITION => $this->{AttributeTemplateSchema::POSITION},
            AttributeTemplateSchema::CREATED_AT => $this->{AttributeTemplateSchema::CREATED_AT},
            AttributeTemplateSchema::UPDATED_AT => $this->{AttributeTemplateSchema::UPDATED_AT},
            AttributeTemplateSchema::RES_GROUPS => AttributeGroupResource::collection($this->whenLoaded(AttributeTemplateSchema::RES_GROUPS)),
            AttributeTemplateSchema::RES_GROUPS_COUNT => $this->{AttributeTemplateSchema::RES_GROUPS_COUNT},
        ];
    }
}
