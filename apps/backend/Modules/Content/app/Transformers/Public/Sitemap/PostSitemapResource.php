<?php

namespace Modules\Content\Transformers\Public\Sitemap;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Content\Schemas\Post\PostSchema;
use Modules\Content\Schemas\Post\PostTranslationSchema as PTSchema;
use Modules\Core\Transformers\ResolvesLanguageCodesTrait;

class PostSitemapResource extends JsonResource
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
            PostSchema::ID => $this->resource->{PostSchema::ID},
            PostSchema::UPDATED_AT => $this->resource->{PostSchema::UPDATED_AT},
            PostSchema::RES_TRANSLATIONS => $this->resource
                ->getRelation(PostSchema::RES_TRANSLATIONS)
                ->filter(fn ($translation) => ! is_null($translation->{PTSchema::LANGUAGE_ID}))
                ->map(fn ($translation) => [
                    PTSchema::COL_LANGUAGE => $this->languageCode($translation->{PTSchema::LANGUAGE_ID}),
                    PTSchema::SLUG => $translation->{PTSchema::SLUG},
                ])
                ->values(),
        ];
    }
}
