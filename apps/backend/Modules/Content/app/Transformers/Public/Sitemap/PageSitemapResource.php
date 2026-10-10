<?php

namespace Modules\Content\Transformers\Public\Sitemap;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Content\Schemas\Page\PageSchema;
use Modules\Content\Schemas\Page\PageTranslationSchema as PagTSchema;
use Modules\Core\Transformers\ResolvesLanguageCodesTrait;

class PageSitemapResource extends JsonResource
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
            PageSchema::ID => $this->resource->{PageSchema::ID},
            PageSchema::UPDATED_AT => $this->resource->{PageSchema::UPDATED_AT},
            PageSchema::RES_TRANSLATIONS => $this->resource
                ->getRelation(PageSchema::RES_TRANSLATIONS)
                ->filter(fn ($translation) => ! is_null($translation->{PagTSchema::LANGUAGE_ID}))
                ->map(fn ($translation) => [
                    PagTSchema::COL_LANGUAGE => $this->languageCode($translation->{PagTSchema::LANGUAGE_ID}),
                    PagTSchema::SLUG => $translation->{PagTSchema::SLUG},
                ])
                ->values(),
        ];
    }
}
