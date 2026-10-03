<?php

namespace Modules\Content\Transformers\Public\PostCategory;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Content\Schemas\PostCategory\PostCategorySchema;
use Modules\Content\Schemas\PostCategory\PostCategoryTranslationSchema as PCTSchema;
use Modules\Core\Support\ResolvesPublicLanguage;

class PostCategoryResource extends JsonResource
{
    use ResolvesPublicLanguage;

    /**
     * Transform the resource into an array. The category is served in the
     * visitor's language (Accept-Language), falling back to the default.
     */
    public function toArray(Request $request): array
    {
        $translation = $this->resource->translations
            ->firstWhere(PCTSchema::LANGUAGE_ID, $this->resolvePublicLanguageId($request))
            ?? $this->resource->translations->first();

        return [
            PostCategorySchema::ID => $this->resource->{PostCategorySchema::ID},
            PostCategorySchema::PARENT_ID => $this->resource->{PostCategorySchema::PARENT_ID},
            PostCategorySchema::POSITION => $this->resource->{PostCategorySchema::POSITION},
            PCTSchema::TITLE => $translation?->{PCTSchema::TITLE},
            PCTSchema::SLUG => $translation?->{PCTSchema::SLUG},
            PCTSchema::DESCRIPTION => $translation?->{PCTSchema::DESCRIPTION},
        ];
    }
}
