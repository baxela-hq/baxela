<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Auth\Models\User;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Models\ProductTranslation;
use Modules\Catalog\Models\Variant;
use Modules\Core\Contracts\Gateways\Order\DTOs\CreateOrderInput;
use Modules\Core\Contracts\Gateways\User\DTOs\AddressDto;
use Modules\Discount\Models\Coupon;
use Modules\Discount\Models\Redemption;
use Modules\Discount\Schemas\Coupon\CouponSchema;
use Modules\Discount\Schemas\Redemption\RedemptionSchema;
use Modules\Order\Gateways\OrderGateway;
use Modules\Order\Models\Order;
use Modules\Order\Schemas\Order\OrderSchema;
use Modules\Order\Schemas\OrderItem\OrderItemSchema;
use Modules\Order\Tests\Feature\HelperTrait;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

function couponOrderInput(Variant $variant, ?Coupon $coupon, int $discountMinor = 0): CreateOrderInput
{
    $input = new CreateOrderInput;
    $input->cart_items = [[
        OrderItemSchema::VARIANT_ID => $variant->id,
        OrderItemSchema::PRICE_SNAPSHOT => 150,
        OrderItemSchema::PRODUCT_NAME_SNAPSHOT => 'Test Product',
        OrderItemSchema::QUANTITY => 2,
    ]];
    $input->address = AddressDto::fill([
        'full_name' => 'Jane Doe',
        'phone' => '+123456789',
        'address_line' => '1 Main St',
        'city' => 'Springfield',
        'postal_code' => '12345',
        'country_code' => 'US',
    ]);
    $input->shipping_method_name = 'Express';
    $input->shipping_cost = 5.0;
    $input->coupon_id = is_null($coupon) ? null : (int) $coupon->id;
    $input->coupon_code = $coupon?->{CouponSchema::CODE};
    $input->discount_minor = $discountMinor;

    return $input;
}

function variantForOrder(): Variant
{
    $product = Product::factory()->create();
    $variant = Variant::factory()->ofProduct($product)->create();
    ProductTranslation::query()->create([
        'product_id' => $product->id,
        'language_id' => 1,
        'title' => 'Test Product',
        'slug' => 'test-product',
        'content' => 'Test content',
    ]);

    return $variant;
}

it('snapshots the coupon onto the order, records the redemption and totals in minor units', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $coupon = Coupon::factory()->create([CouponSchema::CODE => 'SAVE30']);
    $input = couponOrderInput(variantForOrder(), $coupon, 3000); // 30.00 off

    $orderCode = app(OrderGateway::class)->createFromCart($input);

    $order = Order::query()->where(OrderSchema::ORDER_CODE, $orderCode)->first();

    // 150 × 2 − 30 + 5 = 275, all in integer minor units
    expect($order)->not->toBeNull()
        ->and((float) $order->{OrderSchema::TOTAL_AMOUNT})->toBe(275.0)
        ->and($order->{OrderSchema::COUPON_CODE})->toBe('SAVE30')
        ->and((float) $order->{OrderSchema::DISCOUNT_AMOUNT})->toBe(30.0);

    $redemption = Redemption::query()->where(RedemptionSchema::ORDER_ID, $order->{OrderSchema::ID})->first();

    expect($redemption)->not->toBeNull()
        ->and((float) $redemption->{RedemptionSchema::DISCOUNT_AMOUNT})->toBe(30.0)
        ->and((int) $coupon->refresh()->{CouponSchema::USAGE_COUNT})->toBe(1);
});

it('aborts order creation when the authoritative redemption refuses capacity', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    // Limit already exhausted by another customer (simulating a concurrent
    // consumption that happened after the advisory evaluation)
    $coupon = Coupon::factory()->create([
        CouponSchema::USAGE_LIMIT => 1,
        CouponSchema::USAGE_COUNT => 1,
    ]);
    Redemption::query()->create([
        RedemptionSchema::COUPON_ID => $coupon->id,
        RedemptionSchema::ORDER_ID => 900,
        RedemptionSchema::USER_ID => 42,
        RedemptionSchema::DISCOUNT_AMOUNT => 10,
    ]);

    $orderCode = app(OrderGateway::class)->createFromCart(couponOrderInput(variantForOrder(), $coupon, 1000));

    // the freshly created order rolled back with the refused redemption
    expect($orderCode)->toBeNull()
        ->and(Order::count())->toBe(0)
        ->and(Redemption::query()->where(RedemptionSchema::ORDER_ID, 900)->count())->toBe(1)
        ->and((int) $coupon->refresh()->{CouponSchema::USAGE_COUNT})->toBe(1);
});

it('rolls the order, redemption and counter back together when the caller fails afterwards', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $coupon = Coupon::factory()->create();

    try {
        DB::transaction(function () use ($coupon): void {
            app(OrderGateway::class)->createFromCart(couponOrderInput(variantForOrder(), $coupon, 500));

            // any later checkout step failing must take the order AND the
            // redemption AND the counter with it — recordRedemption never
            // commits independently
            throw new RuntimeException('later checkout step failed');
        });
    } catch (RuntimeException) {
    }

    expect(Order::count())->toBe(0)
        ->and(Redemption::count())->toBe(0)
        ->and((int) $coupon->refresh()->{CouponSchema::USAGE_COUNT})->toBe(0);
});
