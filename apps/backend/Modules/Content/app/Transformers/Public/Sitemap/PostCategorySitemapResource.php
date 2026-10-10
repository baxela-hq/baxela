<?php

namespace Modules\Content\Transformers\Public\Sitemap;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Content\Schemas\PostCategory\PostCategorySchema;
use Modules\Content\Schemas\PostCategory\PostCategoryTranslationSchema as PCTSchema;
use Modules\Core\Transformers\ResolvesLanguageCodesTrait;

class PostCategorySitemapResource extends JsonResource
{
    use ResolvesLanguageCodesTrait;

    /**
     * Transform the resource into an array. Translations without a
     * language are skipped — admin flows always resolve a language_id,
     * and a URL locale can't be derived from a null one.
     */
    public function toArray(Request $request): array
    {
        return [
            PostCategorySchema::ID => $this->resource->{PostCategorySchema::ID},
            PostCategorySchema::UPDATED_AT => $this->resource->{PostCategorySchema::UPDATED_AT},
            PostCategorySchema::RES_TRANSLATIONS => $this->resource
                ->getRelation(PostCategorySchema::RES_TRANSLATIONS)
                ->filter(fn ($translation) => ! is_null($translation->{PCTSchema::LANGUAGE_ID}))
                ->map(fn ($translation) => [
                    PCTSchema::COL_LANGUAGE => $this->languageCode($translation->{PCTSchema::LANGUAGE_ID}),
                    PCTSchema::SLUG => $translation->{PCTSchema::SLUG},
                ])
                ->values(),
        ];
    }
}
