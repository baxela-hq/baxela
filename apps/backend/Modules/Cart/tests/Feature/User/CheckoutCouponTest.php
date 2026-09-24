<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\Models\User;
use Modules\Cart\Models\Cart;
use Modules\Cart\Models\CartItem;
use Modules\Cart\Schemas\Cart\CartSchema;
use Modules\Cart\Schemas\CartItem\CartItemSchema;
use Modules\Cart\Tests\Feature\HelperTrait;
use Modules\Core\Contracts\Gateways\Inventory\InventoryGatewayInterface;
use Modules\Discount\Models\Coupon;
use Modules\Discount\Models\Redemption;
use Modules\Discount\Schemas\Coupon\CouponSchema;
use Modules\Discount\Schemas\Redemption\RedemptionSchema;
use Modules\Order\Models\Order;
use Modules\Order\Schemas\Order\OrderSchema;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

function couponCart(User $user, int $price = 150, int $quantity = 2): Cart
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

function applyCouponToCart(User $user, Cart $cart, array $overrides = []): Coupon
{
    $coupon = Coupon::factory()->create(array_merge([
        CouponSchema::CODE => 'SAVE30',
        CouponSchema::TYPE => 'fixed',
        CouponSchema::VALUE => 30,
    ], $overrides));

    $cart->{CartSchema::COUPON_CODE} = $coupon->{CouponSchema::CODE};
    $cart->save();

    return $coupon;
}

it('checks out with a coupon: discounted total, snapshots, redemption recorded', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $variant = $this->variantWithStock(10);
    $address = $this->addressForUser($user);
    $method = $this->shippingMethodForCountry(price: 5.0);
    $cart = couponCart($user); // subtotal 150 × 2 = 300
    $coupon = applyCouponToCart($user, $cart); // fixed 30 off

    $response = $this->postJson($this->baseUrl('/user/checkout'), [
        'address_id' => $address->id,
        'shipping_method_id' => $method->id,
    ])->assertOk();

    $order = Order::query()
        ->where(OrderSchema::ORDER_CODE, $response->json('data.order_code'))
        ->first();

    // 300 − 30 + 5 = 275, computed in integer minor units
    expect($order)->not->toBeNull()
        ->and((float) $order->{OrderSchema::TOTAL_AMOUNT})->toBe(275.0)
        ->and($order->{OrderSchema::COUPON_CODE})->toBe('SAVE30')
        ->and((int) $order->{OrderSchema::COUPON_ID})->toBe((int) $coupon->id)
        ->and((float) $order->{OrderSchema::DISCOUNT_AMOUNT})->toBe(30.0);

    $redemption = Redemption::query()
        ->where(RedemptionSchema::ORDER_ID, $order->{OrderSchema::ID})
        ->first();

    expect($redemption)->not->toBeNull()
        ->and((int) $redemption->{RedemptionSchema::COUPON_ID})->toBe((int) $coupon->id)
        ->and((int) $redemption->{RedemptionSchema::USER_ID})->toBe((int) $user->id)
        ->and((int) $coupon->refresh()->{CouponSchema::USAGE_COUNT})->toBe(1)
        // the cart and its coupon are torn down with the checkout
        ->and(Cart::query()->where(CartSchema::USER_ID, $user->id)->exists())->toBeFalse();
});

it('recalculates the discount when the cart grows between apply and checkout', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $variant = $this->variantWithStock(10);
    $address = $this->addressForUser($user);
    $cart = couponCart($user, price: 100, quantity: 1); // subtotal 100
    applyCouponToCart($user, $cart, [ // 15% off
        CouponSchema::CODE => 'PCT15',
        CouponSchema::TYPE => 'percent',
        CouponSchema::VALUE => 15,
    ]);

    // cart doubles before checkout → 15% of 200, not of 100
    CartItem::query()
        ->where(CartItemSchema::CART_ID, $cart->id)
        ->update([CartItemSchema::QUANTITY => 2]);

    $response = $this->postJson($this->baseUrl('/user/checkout'), [
        'address_id' => $address->id,
    ])->assertOk();

    $order = Order::query()
        ->where(OrderSchema::ORDER_CODE, $response->json('data.order_code'))
        ->first();

    expect((float) $order->{OrderSchema::DISCOUNT_AMOUNT})->toBe(30.0) // 15% of 200
        ->and((float) $order->{OrderSchema::TOTAL_AMOUNT})->toBe(170.0);
});

it('rejects checkout when the coupon was invalidated after being applied', function (callable $invalidate, string $code) {
    $user = User::factory()->create();
    $this->actingAs($user);

    $variant = $this->variantWithStock(10);
    $address = $this->addressForUser($user);
    $cart = couponCart($user);
    $coupon = applyCouponToCart($user, $cart, [CouponSchema::MIN_ORDER_AMOUNT => 100]);

    $invalidate($coupon, $cart);

    $this->postJson($this->baseUrl('/user/checkout'), [
        'address_id' => $address->id,
    ])->assertStatus(400)->assertJsonPath('code', $code);

    expect(Order::count())->toBe(0)
        ->and(Redemption::count())->toBe(0)
        ->and((int) $coupon->refresh()->{CouponSchema::USAGE_COUNT})->toBe(0);
})->with([
    'deactivated' => [
        fn (Coupon $coupon) => $coupon->update([CouponSchema::IS_ACTIVE => false]),
        'cart.coupon.inactive',
    ],
    'expired' => [
        fn (Coupon $coupon) => $coupon->update([CouponSchema::ENDS_AT => now()->subMinute()]),
        'cart.coupon.expired',
    ],
]);

it('rejects checkout when the cart drops below the coupon minimum after apply', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $variant = $this->variantWithStock(10);
    $address = $this->addressForUser($user);
    $cart = couponCart($user); // subtotal 300
    applyCouponToCart($user, $cart, [CouponSchema::MIN_ORDER_AMOUNT => 200]);

    // cart shrinks to 150 → below the 200 minimum
    CartItem::query()
        ->where(CartItemSchema::CART_ID, $cart->id)
        ->update([CartItemSchema::QUANTITY => 1]);

    $this->postJson($this->baseUrl('/user/checkout'), [
        'address_id' => $address->id,
    ])->assertStatus(400)->assertJsonPath('code', 'cart.coupon.min_order_amount');

    expect(Order::count())->toBe(0);
});

it('rolls the order and redemption back when a later checkout step fails', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $variant = $this->variantWithStock(10);
    $address = $this->addressForUser($user);
    $cart = couponCart($user);
    $coupon = applyCouponToCart($user, $cart);

    // Pre-check sees stock, the actual decrement fails — simulating a
    // concurrent sell-out between the two gates inside the transaction
    $inventory = Mockery::mock(InventoryGatewayInterface::class);
    $inventory->shouldReceive('availableQuantity')->andReturn(10);
    $inventory->shouldReceive('decrement')->andReturn(false);
    $this->instance(InventoryGatewayInterface::class, $inventory);

    $this->postJson($this->baseUrl('/user/checkout'), [
        'address_id' => $address->id,
    ])->assertStatus(400);

    // order, redemption AND the counter all rolled back together
    expect(Order::count())->toBe(0)
        ->and(Redemption::count())->toBe(0)
        ->and((int) $coupon->refresh()->{CouponSchema::USAGE_COUNT})->toBe(0);
});
