<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\Models\User;
use Modules\Cart\Models\Cart;
use Modules\Cart\Schemas\Cart\CartSchema;
use Modules\Cart\Tests\Feature\HelperTrait;
use Tests\TestCase;

uses(TestCase::class);
uses(RefreshDatabase::class);
uses(HelperTrait::class);

it('creates a guest cart on the first add and lists its items', function () {
    $variant = $this->variantWithStock(10);
    $token = $this->cartToken();

    $this->postJson($this->baseUrl('/public/cart-items'), [
        'variant_id' => $variant->id,
        'quantity' => 2,
    ], ['X-Cart-Token' => $token])
        ->assertOk()
        ->assertJsonPath('data.quantity', 2);

    expect(Cart::query()->where(CartSchema::TOKEN, $token)->exists())->toBeTrue();

    $this->getJson($this->baseUrl('/public/cart-items'), ['X-Cart-Token' => $token])
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('sums quantities when the same variant is added again', function () {
    $variant = $this->variantWithStock(10);
    $token = $this->cartToken();
    $headers = ['X-Cart-Token' => $token];

    $this->postJson($this->baseUrl('/public/cart-items'), ['variant_id' => $variant->id, 'quantity' => 2], $headers)->assertOk();
    $this->postJson($this->baseUrl('/public/cart-items'), ['variant_id' => $variant->id, 'quantity' => 3], $headers)
        ->assertOk()
        ->assertJsonPath('data.quantity', 5);

    $this->getJson($this->baseUrl('/public/cart-items'), $headers)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.quantity', 5);
});

it('lists an empty cart without creating any rows', function () {
    $token = $this->cartToken();

    $this->getJson($this->baseUrl('/public/cart-items'), ['X-Cart-Token' => $token])
        ->assertOk()
        ->assertJsonCount(0, 'data');

    expect(Cart::count())->toBe(0);
});

it('rejects a request without a cart token', function () {
    $this->getJson($this->baseUrl('/public/cart-items'))
        ->assertStatus(400)
        ->assertJsonPath('code', 'cart.token.missing');
});

it('rejects a malformed cart token', function () {
    $this->getJson($this->baseUrl('/public/cart-items'), ['X-Cart-Token' => 'not-a-uuid'])
        ->assertStatus(400)
        ->assertJsonPath('code', 'cart.token.invalid');
});

it('never accepts the cart token from the query string', function () {
    $token = $this->cartToken();

    $this->getJson($this->baseUrl('/public/cart-items').'?cart_token='.$token)
        ->assertStatus(400)
        ->assertJsonPath('code', 'cart.token.missing');
});

it('isolates guest carts from each other', function () {
    $variant = $this->variantWithStock(10);
    $tokenA = $this->cartToken();
    $tokenB = $this->cartToken();

    $this->postJson($this->baseUrl('/public/cart-items'), ['variant_id' => $variant->id, 'quantity' => 1], ['X-Cart-Token' => $tokenA])->assertOk();

    $this->getJson($this->baseUrl('/public/cart-items'), ['X-Cart-Token' => $tokenB])
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('enforces stock on guest adds', function () {
    $variant = $this->variantWithStock(3);

    $this->postJson($this->baseUrl('/public/cart-items'), ['variant_id' => $variant->id, 'quantity' => 5], ['X-Cart-Token' => $this->cartToken()])
        ->assertStatus(400)
        ->assertJsonPath('code', 'cart.item.insufficient_stock');
});

it('updates and deletes guest cart items', function () {
    $variant = $this->variantWithStock(10);
    $token = $this->cartToken();
    $headers = ['X-Cart-Token' => $token];

    $itemId = $this->postJson($this->baseUrl('/public/cart-items'), ['variant_id' => $variant->id, 'quantity' => 1], $headers)
        ->assertOk()->json('data.id');

    $this->patchJson($this->baseUrl('/public/cart-items/'.$itemId), ['quantity' => 4], $headers)
        ->assertOk()
        ->assertJsonPath('data.quantity', 4);

    $this->deleteJson($this->baseUrl('/public/cart-items/'.$itemId), [], $headers)
        ->assertNoContent();

    $this->getJson($this->baseUrl('/public/cart-items'), $headers)
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('returns 404 for updates against a cart that never existed', function () {
    $this->patchJson($this->baseUrl('/public/cart-items/1'), ['quantity' => 2], ['X-Cart-Token' => $this->cartToken()])
        ->assertNotFound();
});

it('keeps authenticated user carts untouched by guest flows', function () {
    $user = User::factory()->create();
    $userCart = Cart::factory()->create([CartSchema::USER_ID => $user->id]);
    $variant = $this->variantWithStock(10);

    $this->postJson($this->baseUrl('/public/cart-items'), ['variant_id' => $variant->id, 'quantity' => 1], ['X-Cart-Token' => $this->cartToken()])
        ->assertOk();

    expect($userCart->items()->count())->toBe(0)
        ->and($userCart->refresh()->{CartSchema::TOKEN})->toBeNull();
});
