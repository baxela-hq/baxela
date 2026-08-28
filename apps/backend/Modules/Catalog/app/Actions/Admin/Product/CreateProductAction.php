<?php

namespace Modules\Catalog\Actions\Admin\Product;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Modules\Catalog\Exceptions\Product\CreationFailedException;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Schemas\Product\ProductAttributeValueSchema;
use Modules\Catalog\Schemas\Product\ProductSchema;
use Modules\Catalog\Schemas\Variant\VariantSchema as VSchema;
use Modules\Core\Contracts\Events\Catalog\ProductCreatedEvent;
use Throwable;

class CreateProductAction
{
    use FiltersProductSeoTrait;

    /**
     * @throws CreationFailedException|Throwable
     */
    public function handle(array $data): Model
    {
        try {
            DB::beginTransaction();

            $record = Product::query()->create($data);
            $record->categories()->attach($data[ProductSchema::RES_CATEGORIES]);

            if (isset($data[ProductSchema::RES_SHIPPING])) {
                $record->shipping()->create($data[ProductSchema::RES_SHIPPING]);
            }

            if (isset($data[ProductSchema::RES_IMAGES])) {
                foreach ($data[ProductSchema::RES_IMAGES] as $image) {
                    $record->images()->create($image);
                }
            }
            foreach ($data[ProductSchema::RES_TRANSLATIONS] as $translation) {
                $record->translations()->create($translation);
            }
            foreach ($this->filterProductSeo($data[ProductSchema::RES_SEO] ?? []) as $seo) {
                $record->seo()->create($seo);
            }
            foreach ($data[ProductSchema::RES_VARIANTS] as $variantInput) {
                $variant = $record->variants()->create($variantInput);
                $variant->optionValues()->attach(array_values($variantInput[VSchema::REQ_OPTION_VALUE_IDS]));
            }
            foreach ($data[ProductAttributeValueSchema::REQ_ATTRIBUTE_VALUES] ?? [] as $attributeValue) {
                $record->attributeValues()->create($attributeValue);
            }

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            report($e);
            throw new CreationFailedException;
        }

        $record = $record->fresh();

        //        event(ProductCreatedEvent::fill($record->toArray()));

        return $record->load(
            [
                ProductSchema::RES_TRANSLATIONS,
                ProductSchema::RES_SEO,
                ProductSchema::RES_SHIPPING,
                ProductSchema::RES_VARIANTS.'.'.ProductSchema::RES_OPTION_VALUES,
                ProductSchema::RES_CATEGORIES,
                ProductSchema::RES_IMAGES,
                ProductSchema::RES_ATTRIBUTE_VALUES.'.'.ProductAttributeValueSchema::RES_ATTRIBUTE,
            ]
        );
    }
}
