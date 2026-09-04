<?php

namespace Modules\Catalog\Actions\Admin\Product;

use Illuminate\Database\Eloquent\Model;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Schemas\Product\ProductAttributeValueSchema;
use Modules\Catalog\Schemas\Product\ProductSchema;
use Modules\Catalog\Schemas\Variant\VariantSchema as VSchema;
use Modules\Core\Contracts\Events\Catalog\ProductUpdatedEvent;
use Modules\Core\Contracts\Gateways\Inventory\InventoryGatewayInterface;

class UpdateProductAction
{
    use FiltersProductSeoTrait;

    public function handle(string $id, array $data): Model
    {
        $record = Product::query()->findOrFail($id);
        $record->update($data);
        $record->categories()->sync($data[ProductSchema::RES_CATEGORIES]);

        $record->shipping()->delete();
        if (isset($data[ProductSchema::RES_SHIPPING])) {
            $record->shipping()->create($data[ProductSchema::RES_SHIPPING]);
        }

        if (! empty($data[ProductSchema::RES_IMAGES])) {
            $record->images()->delete();
            foreach ($data[ProductSchema::RES_IMAGES] as $image) {
                $record->images()->create($image);
            }
        }

        $record->translations()->delete();
        foreach ($data[ProductSchema::RES_TRANSLATIONS] as $translation) {
            $record->translations()->create($translation);
        }

        $record->seo()->delete();
        foreach ($this->filterProductSeo($data[ProductSchema::RES_SEO] ?? []) as $seo) {
            $record->seo()->create($seo);
        }

        $record->variants()->delete();
        foreach ($data[ProductSchema::RES_VARIANTS] as $variant) {
            $variantRecord = $record->variants()->create($variant);
            if (! empty($variant[VSchema::REQ_OPTION_VALUE_IDS])) {
                $variantRecord->optionValues()->attach(array_values($variant[VSchema::REQ_OPTION_VALUE_IDS]));
            }

            // The variant quantity is the stock level the admin manages;
            // mirror it into the inventory ledger the shop sells from.
            app(InventoryGatewayInterface::class)->upsertStock(
                (int) $variantRecord->{VSchema::ID},
                (int) $variant[VSchema::QUANTITY],
            );
        }

        // Variants are recreated with fresh ids on every save, so the old
        // ids' ledger rows are now orphans.
        app(InventoryGatewayInterface::class)->pruneOrphanedStocks();

        $record->attributeValues()->delete();
        foreach ($data[ProductAttributeValueSchema::REQ_ATTRIBUTE_VALUES] ?? [] as $attributeValue) {
            $record->attributeValues()->create($attributeValue);
        }

        //        event(ProductUpdatedEvent::fill($record->toArray()));

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
