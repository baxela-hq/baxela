<?php

namespace Modules\Content\Transformers\Public\Post;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Content\Schemas\Post\PostSchema;
use Modules\Content\Schemas\Post\PostTranslationSchema as PTSchema;
use Modules\Content\Transformers\Public\PostCategory\PostCategoryResource;
use Modules\Core\Support\ResolvesPublicLanguage;

class PostResource extends JsonResource
{
    use ResolvesPublicLanguage;

    /**
     * Transform the resource into an array. The post is served in the
     * visitor's language (Accept-Language), falling back to the default.
     */
    public function toArray(Request $request): array
    {
        $translation = $this->resource->translations
            ->firstWhere(PTSchema::LANGUAGE_ID, $this->resolvePublicLanguageId($request))
            ?? $this->resource->translations->first();

        return [
            PostSchema::ID => $this->resource->{PostSchema::ID},
            PostSchema::IS_FEATURED => $this->resource->{PostSchema::IS_FEATURED},
            PTSchema::TITLE => $translation?->{PTSchema::TITLE},
            PTSchema::SLUG => $translation?->{PTSchema::SLUG},
            PTSchema::DESCRIPTION => $translation?->{PTSchema::DESCRIPTION},
            PTSchema::CONTENT => $translation?->{PTSchema::CONTENT},
            PostSchema::CREATED_AT => $this->resource->{PostSchema::CREATED_AT},
            PostSchema::UPDATED_AT => $this->resource->{PostSchema::UPDATED_AT},
            PostSchema::RES_CATEGORIES => PostCategoryResource::collection($this->whenLoaded(PostSchema::RES_CATEGORIES)),
        ];
    }
}
