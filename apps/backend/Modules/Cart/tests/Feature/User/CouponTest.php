<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\Models\User;
use Modules\Cart\Models\Cart;
use Modules\Cart\Models\CartItem;
use Modules\Cart\Schemas\Cart\CartSchema;
use Modules\Cart\Schemas\CartItem\CartItemSchema;
use Modules\Cart\Tests\Feature\HelperTrait;
use Modules\Discount\Models\Coupon;
use Modules\Discount\Models\Redemption;
use Modules\Discount\Schemas\Coupon\CouponSchema;
use Modules\Discount\Schemas\Redemption\RedemptionSchema;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

function userCart(User $user, int $price = 100, int $quantity = 2): Cart
{
    $cart = Cart::query()->firstOrCreate(
        [CartSchema::USER_ID => $user->id],
        [CartSchema::TOKEN => null],
    );

    CartItem::query()->create([
        CartItemSchema::CART_ID => $cart->id,
        CartItemSchema::VARIANT_ID => 1,
        CartItemSchema::QUANTITY => $quantity,
        CartItemSchema::PRICE_SNAPSHOT => $price,
        CartItemSchema::PRODUCT_NAME_SNAPSHOT => 'Test Product',
    ]);

    return $cart;
}

it('applies a valid coupon to the cart and reports the computed discount', function () {
    $user = User::factory()->create();
    userCart($user, price: 100, quantity: 2); // subtotal 200.00
    Coupon::factory()->create([
        CouponSchema::CODE => 'SAVE20',
        CouponSchema::TYPE => 'fixed',
        CouponSchema::VALUE => 20,
    ]);

    $this->actingAs($user)
        ->postJson($this->baseUrl('/user/cart/coupon'), ['code' => ' save20 '])
        ->assertOk()
        ->assertJsonPath('data.coupon.code', 'SAVE20')
        ->assertJsonPath('data.coupon.type', 'fixed')
        ->assertJsonPath('data.discount_amount', '20.00');

    expect(Cart::query()->where(CartSchema::USER_ID, $user->id)->value(CartSchema::COUPON_CODE))->toBe('SAVE20');
});

it('shows the applied coupon with a freshly computed discount', function () {
    $user = User::factory()->create();
    userCart($user, price: 150, quantity: 2); // subtotal 300.00
    Coupon::factory()->create([
        CouponSchema::CODE => 'PCT10',
        CouponSchema::TYPE => 'percent',
        CouponSchema::VALUE => 10,
    ]);

    $this->actingAs($user)
        ->postJson($this->baseUrl('/user/cart/coupon'), ['code' => 'PCT10'])
        ->assertOk();

    $this->actingAs($user)
        ->getJson($this->baseUrl('/user/cart/coupon'))
        ->assertOk()
        ->assertJsonPath('data.coupon.code', 'PCT10')
        ->assertJsonPath('data.discount_amount', '30.00');
});

it('reports no coupon when none is applied', function () {
    $user = User::factory()->create();
    userCart($user);

    $this->actingAs($user)
        ->getJson($this->baseUrl('/user/cart/coupon'))
        ->assertOk()
        ->assertJsonPath('data.coupon', null)
        ->assertJsonPath('data.discount_amount', '0.00');
});

it('removes an applied coupon', function () {
    $user = User::factory()->create();
    $cart = userCart($user);
    $cart->{CartSchema::COUPON_CODE} = 'SAVE20';
    $cart->save();

    $this->actingAs($user)
        ->deleteJson($this->baseUrl('/user/cart/coupon'))
        ->assertNoContent();

    expect($cart->refresh()->{CartSchema::COUPON_CODE})->toBeNull();
});

it('rejects each ineligibility reason with its dotted code', function (array $overrides, string $postCode, string $code) {
    $user = User::factory()->create();
    userCart($user, price: 100, quantity: 1); // subtotal 100.00
    Coupon::factory()->create(array_merge([CouponSchema::CODE => 'BROKEN'], $overrides));

    $this->actingAs($user)
        ->postJson($this->baseUrl('/user/cart/coupon'), ['code' => $postCode])
        ->assertStatus(400)
        ->assertJsonPath('code', $code);
})->with([
    'unknown' => [[], 'MISSING', 'cart.coupon.not_found'],
    'inactive' => [[CouponSchema::IS_ACTIVE => false], 'BROKEN', 'cart.coupon.inactive'],
    'expired' => [[CouponSchema::ENDS_AT => now()->subDay()], 'BROKEN', 'cart.coupon.expired'],
    'usage limit' => [[CouponSchema::USAGE_LIMIT => 1, CouponSchema::USAGE_COUNT => 1], 'BROKEN', 'cart.coupon.usage_limit'],
    'min order amount' => [[CouponSchema::MIN_ORDER_AMOUNT => 200], 'BROKEN', 'cart.coupon.min_order_amount'],
]);

it('rejects applying a coupon to an empty cart', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson($this->baseUrl('/user/cart/coupon'), ['code' => 'SAVE20'])
        ->assertStatus(400)
        ->assertJsonPath('code', 'cart.checkout.empty');
});

it('self-heals: a coupon invalidated after applying is cleared and reported as none', function () {
    $user = User::factory()->create();
    $cart = userCart($user);
    $coupon = Coupon::factory()->create([CouponSchema::CODE => 'GONE']);
    $cart->{CartSchema::COUPON_CODE} = 'GONE';
    $cart->save();

    // deactivated after the customer applied it
    $coupon->update([CouponSchema::IS_ACTIVE => false]);

    $this->actingAs($user)
        ->getJson($this->baseUrl('/user/cart/coupon'))
        ->assertOk()
        ->assertJsonPath('data.coupon', null)
        ->assertJsonPath('data.discount_amount', '0.00');

    expect($cart->refresh()->{CartSchema::COUPON_CODE})->toBeNull();
});

it('replaces the applied coupon only when the new code is valid', function () {
    $user = User::factory()->create();
    $cart = userCart($user);
    Coupon::factory()->create([
        CouponSchema::CODE => 'FIRST',
        CouponSchema::TYPE => 'fixed',
        CouponSchema::VALUE => 5,
    ]);
    Coupon::factory()->create([
        CouponSchema::CODE => 'SECOND',
        CouponSchema::TYPE => 'fixed',
        CouponSchema::VALUE => 10,
    ]);

    $this->actingAs($user)
        ->postJson($this->baseUrl('/user/cart/coupon'), ['code' => 'FIRST'])
        ->assertOk();

    // invalid new code → error, old coupon stays applied
    $this->actingAs($user)
        ->postJson($this->baseUrl('/user/cart/coupon'), ['code' => 'NOPE'])
        ->assertStatus(400)
        ->assertJsonPath('code', 'cart.coupon.not_found');

    expect($cart->refresh()->{CartSchema::COUPON_CODE})->toBe('FIRST');

    // valid new code → replaces
    $this->actingAs($user)
        ->postJson($this->baseUrl('/user/cart/coupon'), ['code' => 'SECOND'])
        ->assertOk()
        ->assertJsonPath('data.coupon.code', 'SECOND');

    expect($cart->refresh()->{CartSchema::COUPON_CODE})->toBe('SECOND');
});

it('reports the per-user limit from retained redemptions', function () {
    $user = User::factory()->create();
    userCart($user);
    $coupon = Coupon::factory()->create([CouponSchema::CODE => 'ONCE', CouponSchema::PER_USER_LIMIT => 1]);

    Redemption::query()->create([
        RedemptionSchema::COUPON_ID => $coupon->id,
        RedemptionSchema::ORDER_ID => 77,
        RedemptionSchema::USER_ID => $user->id,
        RedemptionSchema::DISCOUNT_AMOUNT => 10,
    ]);

    $this->actingAs($user)
        ->postJson($this->baseUrl('/user/cart/coupon'), ['code' => 'ONCE'])
        ->assertStatus(400)
        ->assertJsonPath('code', 'cart.coupon.per_user_limit');
});
