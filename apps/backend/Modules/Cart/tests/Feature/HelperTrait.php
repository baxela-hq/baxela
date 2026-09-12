<?php

namespace Modules\Cart\Tests\Feature;

use Illuminate\Support\Str;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Models\Variant;
use Modules\Inventory\Models\InventoryStock;

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
        $variant = Variant::factory()->ofProduct($product)->create();
        InventoryStock::factory()->ofVariant($variant, $quantity)->create();

        return $variant;
    }
}
