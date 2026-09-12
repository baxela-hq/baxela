<?php

namespace Modules\Cart\Tests\Feature;

use Illuminate\Support\Str;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Models\Variant;
use Modules\Inventory\Models\InventoryStock;
use Modules\Shipping\Models\Method;
use Modules\Shipping\Models\MethodTranslation;
use Modules\Shipping\Models\Rate;
use Modules\Shipping\Models\Zone;
use Modules\User\Models\Address;

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

    /**
     * A shipping address owned by the user, ready for checkout. The
     * country defaults to a zone-agnostic value because the checkout
     * fixture's zone is a "rest of world" fallback.
     */
    public function addressForUser($user, string $countryCode = 'US'): Address
    {
        return Address::factory()->create([
            'user_id' => $user->id,
            'country_code' => $countryCode,
        ]);
    }

    /**
     * An active shipping method quoted for any country (its zone carries
     * no country rows, so it acts as a rest-of-world fallback).
     */
    public function shippingMethodForCountry(float $price = 500): Method
    {
        $zone = Zone::factory()->create(['is_active' => true]);
        $method = Method::factory()->create(['is_active' => true]);

        MethodTranslation::query()->create([
            'method_id' => $method->id,
            'language_id' => 1,
            'name' => 'Express',
        ]);

        Rate::factory()->create([
            'method_id' => $method->id,
            'zone_id' => $zone->id,
            'price' => $price,
        ]);

        return $method;
    }
}
