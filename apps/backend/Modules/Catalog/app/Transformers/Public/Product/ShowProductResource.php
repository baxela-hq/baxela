<?php

namespace Modules\Catalog\Transformers\Public\Product;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Catalog\Models\Category;
use Modules\Catalog\Models\Image;
use Modules\Catalog\Models\OptionValue;
use Modules\Catalog\Models\Variant;
use Modules\Catalog\Schemas\Category\CategorySchema;
use Modules\Catalog\Schemas\Category\CategoryTranslationSchema as CTSchema;
use Modules\Catalog\Schemas\Image\ImageSchema;
use Modules\Catalog\Schemas\OptionValue\OptionValueSchema;
use Modules\Catalog\Schemas\OptionValue\OptionValueTranslationSchema as OVTSchema;
use Modules\Catalog\Schemas\Product\ProductSchema;
use Modules\Catalog\Schemas\Product\ProductTranslationSchema as PTSchema;
use Modules\Catalog\Schemas\Variant\VariantSchema;
use Modules\Catalog\Support\ResolvesPublicLanguage;

class ShowProductResource extends JsonResource
{
    use ResolvesPublicLanguage;

    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        $languageId = $this->resolvePublicLanguageId($request);

        $translation = $this->resource->translations
            ->firstWhere(PTSchema::LANGUAGE_ID, $languageId)
            ?? $this->resource->translations->first();

        $variant = $this->resource->variants
            ->firstWhere(VariantSchema::IS_DEFAULT, true)
            ?? $this->resource->variants->first();

        return [
            'id' => $this->resource->{ProductSchema::ID},
            'title' => $translation?->{PTSchema::TITLE},
            'slug' => $translation?->{PTSchema::SLUG},
            'description' => $translation?->{PTSchema::DESCRIPTION},
            'content' => $translation?->{PTSchema::CONTENT},
            'price' => $variant?->{VariantSchema::PRICE},
            'compare_price' => $variant?->{VariantSchema::COMPARE_PRICE},
            'variants' => $this->resource->variants
                ->map(fn (Variant $variant): array => [
                    'id' => $variant->{VariantSchema::ID},
                    'sku' => $variant->{VariantSchema::SKU},
                    'price' => $variant->{VariantSchema::PRICE},
                    'compare_price' => $variant->{VariantSchema::COMPARE_PRICE},
                    'is_default' => $variant->{VariantSchema::IS_DEFAULT},
                    'option_values' => $variant->optionValues
                        ->map(fn (OptionValue $optionValue): array => [
                            'id' => $optionValue->{OptionValueSchema::ID},
                            'title' => $optionValue->translations
                                ->firstWhere(OVTSchema::LANGUAGE_ID, $languageId)
                                ?->{OVTSchema::TITLE}
                                ?? $optionValue->translations->first()?->{OVTSchema::TITLE},
                        ])->all(),
                ])->all(),
            'images' => $this->resource->images
                ->map(fn (Image $image): array => [
                    'id' => $image->{ImageSchema::ID},
                    'url' => $image->{ImageSchema::URL},
                    'collection' => $image->{ImageSchema::COLLECTION},
                    'position' => $image->{ImageSchema::POSITION},
                ])->all(),
            'categories' => $this->resource->categories
                ->map(fn (Category $category): array => [
                    'id' => $category->{CategorySchema::ID},
                    'title' => $category->translations
                        ->firstWhere(CTSchema::LANGUAGE_ID, $languageId)
                        ?->{CTSchema::TITLE}
                        ?? $category->translations->first()?->{CTSchema::TITLE},
                    'slug' => $category->translations
                        ->firstWhere(CTSchema::LANGUAGE_ID, $languageId)
                        ?->{CTSchema::SLUG}
                        ?? $category->translations->first()?->{CTSchema::SLUG},
                ])->all(),
        ];
    }
}
