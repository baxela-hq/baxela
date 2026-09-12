<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\Models\User;
use Modules\Cart\Models\Cart;
use Modules\Cart\Models\CartItem;
use Modules\Cart\Schemas\Cart\CartSchema;
use Modules\Cart\Schemas\CartItem\CartItemSchema;
use Modules\Cart\Tests\Feature\HelperTrait;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

it('creates the user cart on first add and snapshots the live variant price', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $variant = $this->variantWithStock(10);

    $this->postJson($this->baseUrl('/user/cart-items'), [
        'variant_id' => $variant->id,
        'quantity' => 2,
    ])
        ->assertOk()
        ->assertJsonPath('data.quantity', 2);

    $item = CartItem::query()
        ->where(CartItemSchema::VARIANT_ID, $variant->id)
        ->first();

    expect($item)->not->toBeNull()
        ->and((int) $item->{CartItemSchema::PRICE_SNAPSHOT})->toBe((int) $variant->price)
        ->and(Cart::query()->where(CartSchema::USER_ID, $user->id)->count())->toBe(1);
});

it('sums quantities when the same variant is added again', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $variant = $this->variantWithStock(10);

    $this->postJson($this->baseUrl('/user/cart-items'), ['variant_id' => $variant->id, 'quantity' => 2])->assertOk();
    $this->postJson($this->baseUrl('/user/cart-items'), ['variant_id' => $variant->id, 'quantity' => 3])
        ->assertOk()
        ->assertJsonPath('data.quantity', 5);

    $this->getJson($this->baseUrl('/user/cart-items'))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.quantity', 5);
});

it('re-validates live stock on add, counting what the cart already holds', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $variant = $this->variantWithStock(4);

    $this->postJson($this->baseUrl('/user/cart-items'), ['variant_id' => $variant->id, 'quantity' => 3])->assertOk();

    // 3 held + 3 more would exceed the 4 in stock
    $this->postJson($this->baseUrl('/user/cart-items'), ['variant_id' => $variant->id, 'quantity' => 3])
        ->assertStatus(400)
        ->assertJsonPath('code', 'cart.item.insufficient_stock');
});

it('rejects adding an unknown variant', function () {
    $this->actingAs(User::factory()->create());

    $this->postJson($this->baseUrl('/user/cart-items'), ['variant_id' => 999999, 'quantity' => 1])
        ->assertStatus(422)
        ->assertJsonPath('code', 'http.422');
});

it('updates and deletes user cart items', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $variant = $this->variantWithStock(10);

    $itemId = $this->postJson($this->baseUrl('/user/cart-items'), ['variant_id' => $variant->id, 'quantity' => 1])
        ->assertOk()->json('data.id');

    $this->patchJson($this->baseUrl('/user/cart-items/'.$itemId), ['quantity' => 4])
        ->assertOk()
        ->assertJsonPath('data.quantity', 4);

    $this->deleteJson($this->baseUrl('/user/cart-items/'.$itemId))
        ->assertNoContent();

    $this->getJson($this->baseUrl('/user/cart-items'))
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('isolates user carts from each other', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $variant = $this->variantWithStock(10);

    $this->actingAs($userA);
    $this->postJson($this->baseUrl('/user/cart-items'), ['variant_id' => $variant->id, 'quantity' => 1])->assertOk();

    $this->actingAs($userB);
    $this->getJson($this->baseUrl('/user/cart-items'))
        ->assertOk()
        ->assertJsonCount(0, 'data');

    // B updating A's item id stays inside B's (empty) cart — 404, not cross-access
    $itemId = CartItem::query()->where(CartItemSchema::VARIANT_ID, $variant->id)->first()->id;
    $this->patchJson($this->baseUrl('/user/cart-items/'.$itemId), ['quantity' => 2])
        ->assertStatus(404);
});
