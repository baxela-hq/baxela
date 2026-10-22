<?php

namespace Modules\Catalog\Transformers\Public\Product;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Catalog\Models\Category;
use Modules\Catalog\Models\Image;
use Modules\Catalog\Models\OptionValue;
use Modules\Catalog\Models\ProductAttributeValue;
use Modules\Catalog\Models\Variant;
use Modules\Catalog\Schemas\Attribute\AttributeSchema;
use Modules\Catalog\Schemas\Attribute\AttributeTranslationSchema as ATSchema;
use Modules\Catalog\Schemas\Attribute\AttributeTypeEnum;
use Modules\Catalog\Schemas\AttributeValue\AttributeValueTranslationSchema as AVTSchema;
use Modules\Catalog\Schemas\Brand\BrandSchema;
use Modules\Catalog\Schemas\Brand\BrandTranslationSchema as BRTSchema;
use Modules\Catalog\Schemas\Category\CategorySchema;
use Modules\Catalog\Schemas\Category\CategoryTranslationSchema as CTSchema;
use Modules\Catalog\Schemas\Image\ImageSchema;
use Modules\Catalog\Schemas\OptionValue\OptionValueSchema;
use Modules\Catalog\Schemas\OptionValue\OptionValueTranslationSchema as OVTSchema;
use Modules\Catalog\Schemas\Product\ProductAttributeValueSchema as PAVSchema;
use Modules\Catalog\Schemas\Product\ProductSchema;
use Modules\Catalog\Schemas\Product\ProductTranslationSchema as PTSchema;
use Modules\Catalog\Schemas\Variant\VariantSchema;
use Modules\Catalog\Support\PromotionPayload;
use Modules\Catalog\Support\ResolvesPublicLanguage;
use Modules\Core\Support\PicksTranslation;

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
            ?? PicksTranslation::pick($this->resource->translations, $languageId);

        $variant = $this->resource->variants
            ->firstWhere(VariantSchema::IS_DEFAULT, true)
            ?? $this->resource->variants->first();

        $brandTranslation = $this->resource->brand?->translations
            ->firstWhere(BRTSchema::LANGUAGE_ID, $languageId)
            ?? PicksTranslation::pick($this->resource->brand?->translations, $languageId);

        return [
            'id' => $this->resource->{ProductSchema::ID},
            'title' => $translation?->{PTSchema::TITLE},
            'slug' => $translation?->{PTSchema::SLUG},
            'description' => $translation?->{PTSchema::DESCRIPTION},
            'content' => $translation?->{PTSchema::CONTENT},
            'price' => $variant?->{VariantSchema::PRICE},
            'compare_price' => $variant?->{VariantSchema::COMPARE_PRICE},
            'brand' => $this->resource->brand ? [
                'id' => $this->resource->brand->{BrandSchema::ID},
                'title' => $brandTranslation?->{BRTSchema::TITLE},
                'slug' => $brandTranslation?->{BRTSchema::SLUG},
            ] : null,
            // Attached by AppliesProductPromotions when a promotion is live
            'promotion' => PromotionPayload::from($this->resource->getAttribute(ProductSchema::ATTR_PROMOTION)),
            'rating_avg' => $this->resource->{ProductSchema::RATING_AVG},
            'rating_count' => $this->resource->{ProductSchema::RATING_COUNT},
            'variants' => $this->resource->variants
                ->map(fn (Variant $variant): array => [
                    'id' => $variant->{VariantSchema::ID},
                    'sku' => $variant->{VariantSchema::SKU},
                    'price' => $variant->{VariantSchema::PRICE},
                    'compare_price' => $variant->{VariantSchema::COMPARE_PRICE},
                    'promotion' => PromotionPayload::from($variant->getAttribute(VariantSchema::ATTR_PROMOTION)),
                    'is_default' => $variant->{VariantSchema::IS_DEFAULT},
                    'image_url' => $variant->{VariantSchema::RES_IMAGES}->first()?->{ImageSchema::URL},
                    'option_values' => $variant->optionValues
                        ->map(fn (OptionValue $optionValue): array => [
                            'id' => $optionValue->{OptionValueSchema::ID},
                            'title' => $optionValue->translations
                                ->firstWhere(OVTSchema::LANGUAGE_ID, $languageId)
                                ?->{OVTSchema::TITLE}
                                ?? PicksTranslation::pick($optionValue->translations, $languageId)?->{OVTSchema::TITLE},
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
                        ?? PicksTranslation::pick($category->translations, $languageId)?->{CTSchema::TITLE},
                    'slug' => $category->translations
                        ->firstWhere(CTSchema::LANGUAGE_ID, $languageId)
                        ?->{CTSchema::SLUG}
                        ?? PicksTranslation::pick($category->translations, $languageId)?->{CTSchema::SLUG},
                ])->all(),
            'attributes' => $this->resource->attributeValues
                ->sortBy(fn (ProductAttributeValue $row): int => $row->attribute?->{AttributeSchema::POSITION} ?? 0)
                ->map(fn (ProductAttributeValue $row): array => [
                    'id' => $row->{PAVSchema::ID},
                    'code' => $row->attribute?->{AttributeSchema::CODE},
                    'title' => $row->attribute?->translations
                        ->firstWhere(ATSchema::LANGUAGE_ID, $languageId)
                        ?->{ATSchema::TITLE}
                        ?? PicksTranslation::pick($row->attribute?->translations, $languageId)?->{ATSchema::TITLE},
                    'value' => match ($row->attribute?->{AttributeSchema::DATA_TYPE}) {
                        AttributeTypeEnum::SELECT, AttributeTypeEnum::MULTISELECT => $row->attributeValue?->translations
                            ->firstWhere(AVTSchema::LANGUAGE_ID, $languageId)
                            ?->{AVTSchema::TITLE}
                            ?? PicksTranslation::pick($row->attributeValue?->translations, $languageId)?->{AVTSchema::TITLE},
                        AttributeTypeEnum::NUMBER => $row->{PAVSchema::NUMBER_VALUE},
                        AttributeTypeEnum::BOOLEAN => $row->{PAVSchema::BOOLEAN_VALUE},
                        default => $row->{PAVSchema::TEXT_VALUE},
                    },
                ])->values()->all(),
        ];
    }
}
