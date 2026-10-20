<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\Models\User;
use Modules\Cart\Tests\Feature\HelperTrait;
use Modules\Catalog\Schemas\Product\ProductSchema;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

it('previews the same tax the checkout would charge', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $default = $this->defaultTaxClass();
    $shipping = $this->taxClass(isShipping: true);
    $this->taxRate($default, '8.5');
    $this->taxRate($shipping, '8.5');

    $variant = $this->variantWithStock(10, price: 100);
    $this->cartWithItem($user, $variant->id, quantity: 2, price: 100);

    $address = $this->addressForUser($user, 'US');
    $method = $this->shippingMethodForCountry(price: 5.0);

    $this->previewTax( $address, $method)->assertOk()
        ->assertJsonPath('data.tax_amount', '17.43')
        ->assertJsonPath('data.shipping_tax_included', true)
        ->assertJsonPath('data.shipping_tax_amount', '0.43')
        ->assertJsonPath('data.lines.0.variant_id', $variant->id)
        ->assertJsonPath('data.lines.0.tax_amount', '17.00')
        ->assertJsonPath('data.lines.0.rate', '8.5000')
        ->assertJsonPath('data.lines.0.tax_class_id', $default->id);
});

it('previews without shipping tax when no method is given or quotable', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $default = $this->defaultTaxClass();
    $this->taxRate($default, '8.5');

    $variant = $this->variantWithStock(10, price: 100);
    $this->cartWithItem($user, $variant->id, quantity: 1, price: 100);

    $address = $this->addressForUser($user, 'US');

    $this->previewTax($address)->assertOk()
        ->assertJsonPath('data.tax_amount', '8.50')
        ->assertJsonPath('data.shipping_tax_included', false)
        ->assertJsonPath('data.shipping_tax_amount', '0.00');

    // a quotable method with NO shipping-class rate: shipping included but
    // untaxed (missing rate is valid zero-tax configuration)
    $this->previewTax($address, $this->shippingMethodForCountry(price: 5.0))->assertOk()
        ->assertJsonPath('data.shipping_tax_included', true)
        ->assertJsonPath('data.shipping_tax_amount', '0.00')
        ->assertJsonPath('data.tax_amount', '8.50');
});

it('returns a zero preview for an empty cart', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $address = $this->addressForUser($user, 'US');

    $this->previewTax( $address)->assertOk()
        ->assertJsonPath('data.tax_amount', '0.00')
        ->assertJsonPath('data.lines', []);
});

it('requires authentication and a valid owned address', function () {
    $this->getJson($this->baseUrl('/user/cart/tax-preview').'?address_id=1')
        ->assertStatus(401);

    $user = User::factory()->create();
    $this->actingAs($user);

    // a populated cart so the address check is actually reached
    $variant = $this->variantWithStock(10, price: 100);
    $this->cartWithItem($user, $variant->id, quantity: 1, price: 100);

    $this->getJson($this->baseUrl('/user/cart/tax-preview').'?address_id=1')
        ->assertStatus(400)
        ->assertJsonPath('code', 'cart.checkout.invalid_address');

    // someone else's address is treated as unknown
    $this->previewTax($this->addressForUser(User::factory()->create(), 'US'))
        ->assertStatus(400)
        ->assertJsonPath('code', 'cart.checkout.invalid_address');
});

it('surfaces a dangling class reference as its own error, never a fake zero', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->defaultTaxClass();

    $variant = $this->variantWithStock(10, price: 100);
    $variant->product->{ProductSchema::TAX_CLASS_ID} = 424242;
    $variant->product->save();

    $this->cartWithItem($user, $variant->id, quantity: 1, price: 100);

    $this->previewTax( $this->addressForUser($user, 'US'))
        ->assertStatus(500)
        ->assertJsonPath('code', 'tax.configuration.invalid_class');
});
