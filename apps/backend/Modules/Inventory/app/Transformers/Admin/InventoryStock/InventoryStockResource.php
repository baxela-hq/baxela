<?php

namespace Modules\Inventory\Transformers\Admin\InventoryStock;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Catalog\Schemas\Product\ProductSchema;
use Modules\Catalog\Schemas\Variant\VariantSchema;
use Modules\Inventory\Schemas\InventoryStock\InventoryStockSchema;
use Modules\Core\Transformers\ResolvesLanguageCodesTrait;

class InventoryStockResource extends JsonResource
{
    use ResolvesLanguageCodesTrait;

    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            InventoryStockSchema::ID => $this->resource->{InventoryStockSchema::ID},
            InventoryStockSchema::VARIANT_ID => $this->resource->{InventoryStockSchema::VARIANT_ID},
            InventoryStockSchema::QUANTITY => $this->resource->{InventoryStockSchema::QUANTITY},
            InventoryStockSchema::CREATED_AT => $this->resource->{InventoryStockSchema::CREATED_AT},
            InventoryStockSchema::UPDATED_AT => $this->resource->{InventoryStockSchema::UPDATED_AT},
            InventoryStockSchema::RES_VARIANT => $this->whenLoaded(InventoryStockSchema::RES_VARIANT, function () {
                $variant = $this->resource->{InventoryStockSchema::RES_VARIANT};

                return [
                    VariantSchema::ID => $variant->{VariantSchema::ID},
                    VariantSchema::SKU => $variant->{VariantSchema::SKU},
                    VariantSchema::RES_PRODUCT => $this->when(
                        $variant->relationLoaded(VariantSchema::RES_PRODUCT),
                        function () use ($variant) {
                            $product = $variant->{VariantSchema::RES_PRODUCT};

                            return [
                                ProductSchema::ID => $product->{ProductSchema::ID},
                                ProductSchema::RES_TRANSLATIONS => $product->{ProductSchema::RES_TRANSLATIONS}->map(
                                    fn ($translation) => [
                                        'language' => $this->languageCode($translation->language_id),
                                        'title' => $translation->title,
                                    ]
                                )->values(),
                            ];
                        }
                    ),
                ];
            }),
        ];
    }
}
