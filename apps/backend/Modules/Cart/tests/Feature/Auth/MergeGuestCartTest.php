<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\Models\User;
use Modules\Auth\Schemas\User\UserSchema;
use Modules\Cart\Models\Cart;
use Modules\Cart\Models\CartItem;
use Modules\Cart\Schemas\Cart\CartSchema;
use Modules\Cart\Schemas\CartItem\CartItemSchema;
use Modules\Cart\Tests\Feature\HelperTrait;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Models\Variant;
use Modules\Catalog\Schemas\Variant\VariantSchema;
use Modules\Core\Contracts\Events\Auth\UserSignedInEvent;
use Tests\TestCase;

uses(TestCase::class);
uses(RefreshDatabase::class);
uses(HelperTrait::class);

function signInEvent(User $user, ?string $token): void
{
    event(new UserSignedInEvent(
        $user->id,
        $user->{UserSchema::EMAIL},
        now()->toDateTimeString(),
        $token,
    ));
}

function guestCartWithItem(string $token, int $variantId, int $quantity, int $price = 100): Cart
{
    $cart = Cart::query()->firstOrCreate(
        [CartSchema::TOKEN => $token],
        [CartSchema::USER_ID => null],
    );

    CartItem::create([
        CartItemSchema::CART_ID => $cart->id,
        CartItemSchema::VARIANT_ID => $variantId,
        CartItemSchema::QUANTITY => $quantity,
        CartItemSchema::PRICE_SNAPSHOT => $price,
        CartItemSchema::PRODUCT_NAME_SNAPSHOT => 'Test Product',
    ]);

    return $cart;
}

it('merges guest cart quantities into the user cart', function () {
    $user = User::factory()->create();
    $userCart = Cart::factory()->create([CartSchema::USER_ID => $user->id]);
    $variantA = $this->variantWithStock(10);
    $variantB = $this->variantWithStock(10);

    // User already has 2 of A; the guest brings 3 of A and 1 of B.
    CartItem::create([
        CartItemSchema::CART_ID => $userCart->id,
        CartItemSchema::VARIANT_ID => $variantA->id,
        CartItemSchema::QUANTITY => 2,
        CartItemSchema::PRICE_SNAPSHOT => 100,
        CartItemSchema::PRODUCT_NAME_SNAPSHOT => 'Test Product',
    ]);
    $token = $this->cartToken();
    guestCartWithItem($token, $variantA->id, 3);
    guestCartWithItem($token, $variantB->id, 1);

    signInEvent($user, $token);

    $userItems = $userCart->items()->get();
    expect($userItems->count())->toBe(2)
        ->and((int) $userItems->firstWhere(CartItemSchema::VARIANT_ID, $variantA->id)->{CartItemSchema::QUANTITY})->toBe(5)
        ->and((int) $userItems->firstWhere(CartItemSchema::VARIANT_ID, $variantB->id)->{CartItemSchema::QUANTITY})->toBe(1)
        ->and(Cart::query()->where(CartSchema::TOKEN, $token)->exists())->toBeFalse();
});

it('caps the merged quantity at available stock', function () {
    $user = User::factory()->create();
    $userCart = Cart::factory()->create([CartSchema::USER_ID => $user->id]);
    $variant = $this->variantWithStock(4); // stock is below guest(3) + user(2)

    CartItem::create([
        CartItemSchema::CART_ID => $userCart->id,
        CartItemSchema::VARIANT_ID => $variant->id,
        CartItemSchema::QUANTITY => 2,
        CartItemSchema::PRICE_SNAPSHOT => 100,
        CartItemSchema::PRODUCT_NAME_SNAPSHOT => 'Test Product',
    ]);
    $token = $this->cartToken();
    guestCartWithItem($token, $variant->id, 3);

    signInEvent($user, $token);

    expect((int) $userCart->items()->first()->{CartItemSchema::QUANTITY})->toBe(4);
});

it('skips guest items with no stock and leaves the user cart untouched', function () {
    $user = User::factory()->create();
    $userCart = Cart::factory()->create([CartSchema::USER_ID => $user->id]);
    $variant = $this->variantWithStock(5);
    // A variant with no inventory row: availableQuantity() resolves null.
    $unstockedVariant = Variant::factory()->create([
        VariantSchema::PRODUCT_ID => Product::factory()->create()->id,
    ]);

    $token = $this->cartToken();
    guestCartWithItem($token, $variant->id, 2);
    guestCartWithItem($token, $unstockedVariant->id, 1);

    signInEvent($user, $token);

    $userItems = $userCart->items()->get();
    expect($userItems->count())->toBe(1)
        ->and((int) $userItems->first()->{CartItemSchema::QUANTITY})->toBe(2)
        // the guest cart is still consumed in full
        ->and(Cart::query()->where(CartSchema::TOKEN, $token)->exists())->toBeFalse();
});

it('claims the guest cart when the user has none', function () {
    $user = User::factory()->create();
    $variant = $this->variantWithStock(5);
    $token = $this->cartToken();
    $guestCart = guestCartWithItem($token, $variant->id, 2);

    signInEvent($user, $token);

    $claimed = $guestCart->refresh();
    expect((int) $claimed->{CartSchema::USER_ID})->toBe($user->id)
        ->and($claimed->{CartSchema::TOKEN})->toBeNull()
        ->and($claimed->items()->count())->toBe(1)
        ->and(Cart::query()->where(CartSchema::USER_ID, $user->id)->count())->toBe(1);
});

it('is idempotent — a repeated merge does not duplicate quantities', function () {
    $user = User::factory()->create();
    $variant = $this->variantWithStock(10);
    $token = $this->cartToken();
    guestCartWithItem($token, $variant->id, 2);

    signInEvent($user, $token);
    signInEvent($user, $token); // retry / duplicate request

    $userCart = Cart::query()->where(CartSchema::USER_ID, $user->id)->first();
    expect((int) $userCart->items()->first()->{CartItemSchema::QUANTITY})->toBe(2)
        ->and($userCart->items()->count())->toBe(1);
});

it('no-ops without a token or for an unknown token', function () {
    $user = User::factory()->create();
    $userCart = Cart::factory()->create([CartSchema::USER_ID => $user->id]);
    $variant = $this->variantWithStock(5);

    // No token at all.
    signInEvent($user, null);
    // Well-formed token that never had a cart.
    signInEvent($user, $this->cartToken());

    expect($userCart->items()->count())->toBe(0)
        ->and(Cart::count())->toBe(1);
});

it('merges the guest cart through the sign-in endpoint', function () {
    $email = fake()->email();
    $user = User::factory()->create([
        UserSchema::EMAIL => $email,
        UserSchema::PASSWORD => '12345678',
        UserSchema::IS_ACTIVE => true,
    ]);
    $variant = $this->variantWithStock(5);
    $token = $this->cartToken();
    guestCartWithItem($token, $variant->id, 3);

    $this->postJson('api/v1/auth/public/auth/signin', [
        UserSchema::EMAIL => $email,
        UserSchema::PASSWORD => '12345678',
    ], ['X-Cart-Token' => $token])
        ->assertOk();

    $userCart = Cart::query()->where(CartSchema::USER_ID, $user->id)->first();
    expect($userCart)->not->toBeNull()
        ->and((int) $userCart->items()->first()->{CartItemSchema::QUANTITY})->toBe(3)
        ->and(Cart::query()->where(CartSchema::TOKEN, $token)->exists())->toBeFalse();
});
