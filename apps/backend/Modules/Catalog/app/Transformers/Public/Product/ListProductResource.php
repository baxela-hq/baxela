<?php

namespace Modules\Catalog\Transformers\Public\Product;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Catalog\Schemas\Image\ImageSchema;
use Modules\Catalog\Schemas\Product\ProductSchema;
use Modules\Catalog\Schemas\Product\ProductTranslationSchema as PTSchema;
use Modules\Catalog\Schemas\Variant\VariantSchema;
use Modules\Catalog\Support\ResolvesPublicLanguage;

class ListProductResource extends JsonResource
{
    use ResolvesPublicLanguage;

    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        $translation = $this->resource->translations
            ->firstWhere(PTSchema::LANGUAGE_ID, $this->resolvePublicLanguageId($request))
            ?? $this->resource->translations->first();

        $variant = $this->resource->variants
            ->firstWhere(VariantSchema::IS_DEFAULT, true)
            ?? $this->resource->variants->first();

        return [
            'id' => $this->resource->{ProductSchema::ID},
            'title' => $translation?->{PTSchema::TITLE},
            'slug' => $translation?->{PTSchema::SLUG},
            'description' => $translation?->{PTSchema::DESCRIPTION},
            'price' => $variant?->{VariantSchema::PRICE},
            'compare_price' => $variant?->{VariantSchema::COMPARE_PRICE},
            'image_url' => $this->resource->images->first()?->{ImageSchema::URL},
            'created_at' => $this->resource->{ProductSchema::CREATED_AT}?->toIso8601String(),
        ];
    }
}
