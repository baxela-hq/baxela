<?php

namespace Modules\Catalog\Transformers\Public\Sitemap;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Catalog\Schemas\Product\ProductSchema;
use Modules\Catalog\Schemas\Product\ProductTranslationSchema as PTSchema;
use Modules\Core\Transformers\ResolvesLanguageCodesTrait;

class ProductSitemapResource extends JsonResource
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
            ProductSchema::ID => $this->resource->{ProductSchema::ID},
            ProductSchema::UPDATED_AT => $this->resource->{ProductSchema::UPDATED_AT},
            ProductSchema::RES_TRANSLATIONS => $this->resource
                ->getRelation(ProductSchema::RES_TRANSLATIONS)
                ->filter(fn ($translation) => ! is_null($translation->{PTSchema::LANGUAGE_ID}))
                ->map(fn ($translation) => [
                    PTSchema::COL_LANGUAGE => $this->languageCode($translation->{PTSchema::LANGUAGE_ID}),
                    PTSchema::SLUG => $translation->{PTSchema::SLUG},
                ])
                ->values(),
        ];
    }
}
