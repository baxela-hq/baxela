<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\Models\User;
use Modules\Catalog\Models\Product;
use Modules\User\Models\WishlistItem;
use Modules\User\Tests\Feature\HelperTrait;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

it('adds a product to the wishlist and lists it', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $product = Product::factory()->create();

    $this->postJson($this->baseUrl('/user/wishlist-items'), ['product_id' => $product->id])
        ->assertCreated()
        ->assertJsonPath('data.product_id', $product->id);

    $this->getJson($this->baseUrl('/user/wishlist-items'))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.product_id', $product->id);
});

it('treats a duplicate add as idempotent', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $product = Product::factory()->create();

    $this->postJson($this->baseUrl('/user/wishlist-items'), ['product_id' => $product->id])->assertCreated();

    // The duplicate add is acked as a plain OK with the existing row
    $this->postJson($this->baseUrl('/user/wishlist-items'), ['product_id' => $product->id])->assertOk();

    expect(WishlistItem::query()->where('user_id', $user->id)->count())->toBe(1);
});

it('rejects adding an unknown product', function () {
    $this->actingAs(User::factory()->create());

    $this->postJson($this->baseUrl('/user/wishlist-items'), ['product_id' => 999999])
        ->assertStatus(422)
        ->assertJsonPath('code', 'http.422');
});

it('removes a wishlist item by product id', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $product = Product::factory()->create();

    $this->postJson($this->baseUrl('/user/wishlist-items'), ['product_id' => $product->id])->assertCreated();

    $this->deleteJson($this->baseUrl('/user/wishlist-items/'.$product->id))
        ->assertNoContent();

    expect(WishlistItem::query()->where('user_id', $user->id)->count())->toBe(0);

    // removing again is a 404, not a silent success
    $this->deleteJson($this->baseUrl('/user/wishlist-items/'.$product->id))
        ->assertStatus(404);
});

it('isolates wishlists between users', function () {
    $owner = User::factory()->create();
    $product = Product::factory()->create();

    WishlistItem::query()->create([
        'user_id' => $owner->id,
        'product_id' => $product->id,
    ]);

    $this->actingAs(User::factory()->create())
        ->getJson($this->baseUrl('/user/wishlist-items'))
        ->assertOk()
        ->assertJsonCount(0, 'data');
});
