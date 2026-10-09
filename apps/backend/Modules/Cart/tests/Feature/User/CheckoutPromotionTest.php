<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\Models\User;
use Modules\Cart\Models\Cart;
use Modules\Cart\Models\CartItem;
use Modules\Cart\Schemas\Cart\CartSchema;
use Modules\Cart\Schemas\CartItem\CartItemSchema;
use Modules\Cart\Tests\Feature\HelperTrait;
use Modules\Catalog\Models\Variant;
use Modules\Discount\Models\Coupon;
use Modules\Discount\Models\Promotion;
use Modules\Discount\Schemas\Coupon\CouponSchema;
use Modules\Discount\Schemas\Promotion\PromotionSchema;
use Modules\Order\Models\Order;
use Modules\Order\Models\OrderItem;
use Modules\Order\Schemas\OrderItem\OrderItemSchema;
use Modules\Order\Schemas\Order\OrderSchema;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

function promotedCart(User $user, Variant $variant, int $quantity = 2): Cart
{
    $cart = Cart::query()->firstOrCreate(
        [CartSchema::USER_ID => $user->id],
        [CartSchema::TOKEN => null],
    );

    CartItem::query()->create([
        CartItemSchema::CART_ID => $cart->id,
        CartItemSchema::VARIANT_ID => $variant->id,
        CartItemSchema::QUANTITY => $quantity,
        // Stale on purpose when a promotion is live: the refresh must
        // overwrite it with the current effective price
        CartItemSchema::PRICE_SNAPSHOT => $variant->price,
        CartItemSchema::PRODUCT_NAME_SNAPSHOT => 'Test Product',
    ]);

    return $cart;
}

it('refreshes a drifted snapshot to the promoted price when the cart is listed', function () {
    $user = User::factory()->create();
    $variant = $this->variantWithStock(10, price: 150);
    $cart = promotedCart($user, $variant);

    Promotion::factory()->create([
        PromotionSchema::SCOPE => 'all',
        PromotionSchema::TYPE => 'percent',
        PromotionSchema::VALUE => 20,
    ]);

    $this->actingAs($user)
        ->getJson($this->baseUrl('/user/cart-items'))
        ->assertOk()
        ->assertJsonPath('data.0.'.CartItemSchema::PRICE_SNAPSHOT, '120.00')
        ->assertJsonPath('data.0.'.CartItemSchema::PROMOTION.'.type', 'percent')
        ->assertJsonPath('data.0.'.CartItemSchema::PROMOTION.'.value', '20.00');

    // The stored snapshot is the promoted price (native decimal readback
    // loses the 2dp formatting — the wire format above is what matters)
    expect((float) $cart->items()->first()->{CartItemSchema::PRICE_SNAPSHOT})->toBe(120.0);
});

it('charges the promoted price when the promotion started after add-to-cart', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $variant = $this->variantWithStock(10, price: 150);
    $address = $this->addressForUser($user);
    promotedCart($user, $variant); // snapshot 150, no promotion yet

    Promotion::factory()->create([
        PromotionSchema::SCOPE => 'all',
        PromotionSchema::TYPE => 'percent',
        PromotionSchema::VALUE => 20,
    ]);

    $response = $this->postJson($this->baseUrl('/user/checkout'), [
        'address_id' => $address->id,
    ])->assertOk();

    $order = Order::query()
        ->where(OrderSchema::ORDER_CODE, $response->json('data.order_code'))
        ->first();

    // 120 × 2 = 240 — the live promotion wins over the stale snapshot
    expect((float) $order->{OrderSchema::TOTAL_AMOUNT})->toBe(240.0);
});

it('charges the base price when the promotion expired after add-to-cart', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $variant = $this->variantWithStock(10, price: 150);
    $address = $this->addressForUser($user);
    $cart = promotedCart($user, $variant);

    Promotion::factory()->create([
        PromotionSchema::SCOPE => 'all',
        PromotionSchema::TYPE => 'percent',
        PromotionSchema::VALUE => 20,
        PromotionSchema::ENDS_AT => now()->subMinute(),
    ]);

    // The cart briefly carried the promoted price, then the sale ended
    CartItem::query()
        ->where(CartItemSchema::CART_ID, $cart->id)
        ->update([CartItemSchema::PRICE_SNAPSHOT => 120]);

    $response = $this->postJson($this->baseUrl('/user/checkout'), [
        'address_id' => $address->id,
    ])->assertOk();

    $order = Order::query()
        ->where(OrderSchema::ORDER_CODE, $response->json('data.order_code'))
        ->first();

    // Back to the base price: 150 × 2 = 300
    expect((float) $order->{OrderSchema::TOTAL_AMOUNT})->toBe(300.0);

    $item = $order->items()->first();
    expect($item->{OrderItemSchema::BASE_PRICE})->toBeNull()
        ->and($item->{OrderItemSchema::PROMOTION_ID})->toBeNull();
});

it('snapshots the promotion facts on the order items and sums to the total', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $variant = $this->variantWithStock(10, price: 150);
    $address = $this->addressForUser($user);
    $method = $this->shippingMethodForCountry(price: 5.0);
    promotedCart($user, $variant);

    $promotion = Promotion::factory()->create([
        PromotionSchema::SCOPE => 'all',
        PromotionSchema::TYPE => 'percent',
        PromotionSchema::VALUE => 20,
    ]);

    $response = $this->postJson($this->baseUrl('/user/checkout'), [
        'address_id' => $address->id,
        'shipping_method_id' => $method->id,
    ])->assertOk();

    $order = Order::query()
        ->where(OrderSchema::ORDER_CODE, $response->json('data.order_code'))
        ->first();

    $item = OrderItem::query()->where(OrderItemSchema::ORDER_ID, $order->id)->first();

    // base 150 − 20% → discount 30, charged 120; the split is exact
    expect((float) $item->{OrderItemSchema::BASE_PRICE})->toBe(150.0)
        ->and((float) $item->{OrderItemSchema::PROMOTION_DISCOUNT})->toBe(30.0)
        ->and((float) $item->{OrderItemSchema::PRICE_SNAPSHOT})->toBe(120.0)
        ->and((int) $item->{OrderItemSchema::PROMOTION_ID})->toBe((int) $promotion->id)
        // total ≡ Σ item snapshots + shipping, by construction
        ->and((float) $order->{OrderSchema::TOTAL_AMOUNT})->toBe(245.0); // 120×2 + 5

    // Deleting the promotion afterwards leaves the history self-contained
    $promotion->delete();
    expect((float) $item->refresh()->{OrderItemSchema::BASE_PRICE})->toBe(150.0);
});

it('stacks a coupon on top of the promoted subtotal', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $variant = $this->variantWithStock(10, price: 150);
    $address = $this->addressForUser($user);
    $cart = promotedCart($user, $variant);

    Promotion::factory()->create([
        PromotionSchema::SCOPE => 'all',
        PromotionSchema::TYPE => 'percent',
        PromotionSchema::VALUE => 20,
    ]);

    $coupon = Coupon::factory()->create([
        CouponSchema::CODE => 'SAVE20',
        CouponSchema::TYPE => 'fixed',
        CouponSchema::VALUE => 20,
    ]);
    $cart->{CartSchema::COUPON_CODE} = $coupon->{CouponSchema::CODE};
    $cart->save();

    $response = $this->postJson($this->baseUrl('/user/checkout'), [
        'address_id' => $address->id,
    ])->assertOk();

    $order = Order::query()
        ->where(OrderSchema::ORDER_CODE, $response->json('data.order_code'))
        ->first();

    // promoted subtotal 120×2 = 240 − coupon 20 = 220
    expect((float) $order->{OrderSchema::DISCOUNT_AMOUNT})->toBe(20.0)
        ->and((float) $order->{OrderSchema::TOTAL_AMOUNT})->toBe(220.0);
});
