<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Cart\Models\Cart;
use Modules\Cart\Models\CartItem;
use Modules\Cart\Schemas\Cart\CartSchema;
use Modules\Cart\Tests\Feature\HelperTrait;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

it('exports carts and cart items (export-only)', function () {
    $this->actingAs($this->superAdminUser());

    $cart = Cart::query()->create(['user_id' => 7, 'token' => 'tok-1', 'coupon_code' => null]);
    CartItem::query()->create([
        'cart_id' => $cart->id, 'variant_id' => 5, 'price_snapshot' => '100.00',
        'product_name_snapshot' => 'Tee', 'quantity' => 2,
    ]);

    $file = $this->exportedCartDataFile();

    expect($file['module'])->toBe('cart')
        ->and(array_column($file['sections'], 'entity'))->toBe(['carts', 'cart-items'])
        ->and($file['sections'][0]['rows'][0]['payload'][CartSchema::TOKEN])->toBe('tok-1')
        ->and($file['sections'][1]['rows'][0]['payload']['product_name_snapshot'])->toBe('Tee');
});

it('denies the data endpoints to guests', function () {
    $this->getJson($this->baseUrl('/admin/data/export'))->assertStatus(401);
});
