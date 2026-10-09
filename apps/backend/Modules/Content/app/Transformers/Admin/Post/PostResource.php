<?php

namespace Modules\Content\Transformers\Admin\Post;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Content\Schemas\Post\PostSchema;
use Modules\Content\Transformers\Admin\PostCategory\PostCategoryResource;

class PostResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            PostSchema::ID => $this->{PostSchema::ID},
            PostSchema::STATUS => $this->{PostSchema::STATUS},
            PostSchema::PUBLISHED_AT => $this->{PostSchema::PUBLISHED_AT},
            PostSchema::CREATED_AT => $this->{PostSchema::CREATED_AT},
            PostSchema::UPDATED_AT => $this->{PostSchema::UPDATED_AT},
            PostSchema::RES_TRANSLATIONS => PostTranslationResource::collection($this->whenLoaded(PostSchema::RES_TRANSLATIONS)),
            PostSchema::RES_CATEGORIES => PostCategoryResource::collection($this->whenLoaded(PostSchema::RES_CATEGORIES)),
            PostSchema::RES_PRODUCTS => PostProductResource::collection($this->whenLoaded(PostSchema::RES_PRODUCTS)),
            PostSchema::RES_SEO => PostSeoTranslationResource::collection($this->whenLoaded(PostSchema::RES_SEO)),
        ];
    }
}
