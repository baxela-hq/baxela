<?php

namespace Modules\Cart\Tests\Feature;

use Illuminate\Support\Str;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Models\Variant;
use Modules\Catalog\Schemas\Variant\VariantSchema;
use Modules\Inventory\Models\InventoryStock;
use Modules\Inventory\Schemas\InventoryStock\InventoryStockSchema;

trait HelperTrait
{
    public function baseUrl(string $endpoint): string
    {
        return 'api/v1/cart'.$endpoint;
    }

    public function cartToken(): string
    {
        return (string) Str::uuid();
    }

    /**
     * A variant with an inventory stock row so the cart stock gates have a
     * quantity to check against.
     */
    public function variantWithStock(int $quantity): Variant
    {
        $product = Product::factory()->create();
        $variant = Variant::factory()->create([
            VariantSchema::PRODUCT_ID => $product->id,
        ]);
        InventoryStock::factory()->create([
            InventoryStockSchema::VARIANT_ID => $variant->id,
            InventoryStockSchema::QUANTITY => $quantity,
        ]);

        return $variant;
    }
}
