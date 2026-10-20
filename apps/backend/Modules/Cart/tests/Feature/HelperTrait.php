<?php

namespace Modules\Cart\Tests\Feature;

use Illuminate\Support\Str;
use Modules\Cart\Models\Cart;
use Modules\Cart\Models\CartItem;
use Modules\Cart\Schemas\Cart\CartSchema;
use Modules\Cart\Schemas\CartItem\CartItemSchema;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Models\Variant;
use Modules\Catalog\Schemas\Variant\VariantSchema;
use Modules\Inventory\Models\InventoryStock;
use Modules\Shipping\Models\Method;
use Modules\Shipping\Models\MethodTranslation;
use Modules\Shipping\Models\Rate;
use Modules\Shipping\Models\Zone;
use Modules\Tax\Models\TaxClass;
use Modules\Tax\Models\TaxRate;
use Modules\Tax\Schemas\TaxClass\TaxClassSchema;
use Modules\Tax\Schemas\TaxRate\TaxRateSchema;
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
     * quantity to check against. Checkout re-resolves the live variant
     * price, so tests asserting totals pass an explicit deterministic
     * price instead of the factory's random one.
     */
    public function variantWithStock(int $quantity, ?int $price = null): Variant
    {
        $product = Product::factory()->create();

        $attributes = [];
        if ($price !== null) {
            $attributes[VariantSchema::PRICE] = $price;
        }

        $variant = Variant::factory()->ofProduct($product)->create($attributes);
        InventoryStock::factory()->ofVariant($variant, $quantity)->create();

        return $variant;
    }

    /**
     * The default tax class checkout requires — created bare (no rates),
     * so untaxed fixtures keep their exact totals unless a test adds
     * rates for the address country.
     */
    public function defaultTaxClass(): TaxClass
    {
        return TaxClass::query()->firstWhere(TaxClassSchema::IS_DEFAULT, true)
            ?? TaxClass::query()->create([
                TaxClassSchema::NAME => 'Standard',
                TaxClassSchema::IS_DEFAULT => true,
                TaxClassSchema::IS_SHIPPING => false,
            ]);
    }

    public function taxClass(bool $isDefault = false, bool $isShipping = false): TaxClass
    {
        return TaxClass::query()->create([
            TaxClassSchema::NAME => uniqid('class-'),
            TaxClassSchema::IS_DEFAULT => $isDefault,
            TaxClassSchema::IS_SHIPPING => $isShipping,
        ]);
    }

    public function taxRate(TaxClass $class, string $rate, string $country = 'US'): TaxRate
    {
        return TaxRate::query()->create([
            TaxRateSchema::NAME => uniqid('rate-'),
            TaxRateSchema::COUNTRY_CODE => $country,
            TaxRateSchema::TAX_CLASS_ID => $class->id,
            TaxRateSchema::RATE => $rate,
            TaxRateSchema::IS_ACTIVE => true,
        ]);
    }

    /**
     * A user cart with one line per call — subsequent calls add lines to
     * the same cart (a cart holds at most one line per variant).
     */
    public function cartWithItem($user, int $variantId, int $quantity = 2, int $price = 150): Cart
    {
        $cart = Cart::query()->firstOrCreate(
            [CartSchema::USER_ID => $user->id],
            [CartSchema::TOKEN => null],
        );

        CartItem::query()->create([
            CartItemSchema::CART_ID => $cart->id,
            CartItemSchema::VARIANT_ID => $variantId,
            CartItemSchema::QUANTITY => $quantity,
            CartItemSchema::PRICE_SNAPSHOT => $price,
            CartItemSchema::PRODUCT_NAME_SNAPSHOT => 'Test Product',
        ]);

        return $cart;
    }

    public function checkoutCart($user, $address, $method = null)
    {
        return $this->postJson($this->baseUrl('/user/checkout'), [
            'address_id' => $address->id,
            'shipping_method_id' => $method?->id,
        ]);
    }

    public function previewTax($address, $method = null)
    {
        return $this->getJson($this->baseUrl('/user/cart/tax-preview').'?'.http_build_query(array_filter([
            'address_id' => $address?->id,
            'shipping_method_id' => $method?->id,
        ])));
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
