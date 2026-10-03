<?php

namespace Modules\Content\Transformers\Admin\PostCategory;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Content\Schemas\PostCategory\PostCategorySchema;

class PostCategoryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            PostCategorySchema::ID => $this->{PostCategorySchema::ID},
            PostCategorySchema::PARENT_ID => $this->{PostCategorySchema::PARENT_ID},
            PostCategorySchema::POSITION => $this->{PostCategorySchema::POSITION},
            PostCategorySchema::CREATED_AT => $this->{PostCategorySchema::CREATED_AT},
            PostCategorySchema::UPDATED_AT => $this->{PostCategorySchema::UPDATED_AT},
            PostCategorySchema::RES_TRANSLATIONS => PostCategoryTranslationResource::collection($this->whenLoaded(PostCategorySchema::RES_TRANSLATIONS)),
        ];
    }
}
