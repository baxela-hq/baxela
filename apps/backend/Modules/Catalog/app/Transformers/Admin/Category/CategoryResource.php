<?php

namespace Modules\Catalog\Transformers\Admin\Category;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Catalog\Schemas\Category\CategorySchema;
use Modules\Catalog\Transformers\Admin\Attribute\AttributeResource;

class CategoryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            CategorySchema::ID => $this->{CategorySchema::ID},
            CategorySchema::PARENT_ID => $this->{CategorySchema::PARENT_ID},
            CategorySchema::POSITION => $this->{CategorySchema::POSITION},
            CategorySchema::CREATED_AT => $this->{CategorySchema::CREATED_AT},
            CategorySchema::UPDATED_AT => $this->{CategorySchema::UPDATED_AT},
            CategorySchema::RES_TRANSLATIONS => CategoryTranslationResource::collection($this->whenLoaded(CategorySchema::RES_TRANSLATIONS)),
            CategorySchema::RES_ATTRIBUTES => AttributeResource::collection($this->whenLoaded(CategorySchema::RES_ATTRIBUTES)),
        ];
    }
}
