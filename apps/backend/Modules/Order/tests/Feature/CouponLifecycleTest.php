<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\Models\User;
use Modules\Discount\Models\Coupon;
use Modules\Discount\Models\Redemption;
use Modules\Discount\Schemas\Coupon\CouponSchema;
use Modules\Discount\Schemas\Redemption\RedemptionSchema;
use Modules\Order\Models\Order;
use Modules\Order\Schemas\Order\OrderPaymentStatusEnum;
use Modules\Order\Schemas\Order\OrderSchema;
use Modules\Order\Schemas\Order\OrderStatusEnum;
use Modules\Order\Tests\Feature\HelperTrait;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

function couponOrder(array $orderOverrides = []): array
{
    $user = User::factory()->create();
    $coupon = Coupon::factory()->create([CouponSchema::CODE => 'RELEASE']);

    $order = Order::factory()->create(array_merge([
        OrderSchema::USER_ID => $user->id,
        OrderSchema::STATUS => OrderStatusEnum::PENDING,
        OrderSchema::PAYMENT_STATUS => OrderPaymentStatusEnum::UNPAID,
        OrderSchema::EXPIRES_AT => now()->subMinute(),
        OrderSchema::COUPON_ID => $coupon->id,
        OrderSchema::COUPON_CODE => 'RELEASE',
        OrderSchema::DISCOUNT_AMOUNT => 20,
    ], $orderOverrides));

    Redemption::query()->create([
        RedemptionSchema::COUPON_ID => $coupon->id,
        RedemptionSchema::ORDER_ID => $order->{OrderSchema::ID},
        RedemptionSchema::USER_ID => $user->id,
        RedemptionSchema::DISCOUNT_AMOUNT => 20,
    ]);
    $coupon->{CouponSchema::USAGE_COUNT} = 1;
    $coupon->save();

    return [$order, $coupon];
}

it('releases the coupon when an expired unpaid order is cancelled', function () {
    [$order, $coupon] = couponOrder();

    $this->artisan('order:cancel-expired')->assertSuccessful();

    expect($order->refresh()->{OrderSchema::STATUS})->toBe(OrderStatusEnum::CANCELLED)
        ->and(Redemption::query()->where(RedemptionSchema::ORDER_ID, $order->{OrderSchema::ID})->exists())->toBeFalse()
        ->and((int) $coupon->refresh()->{CouponSchema::USAGE_COUNT})->toBe(0);
});

it('keeps the coupon consumed when an expired but already-paid order is cancelled', function () {
    [$order, $coupon] = couponOrder([
        OrderSchema::STATUS => OrderStatusEnum::PROCESSING,
        OrderSchema::PAYMENT_STATUS => OrderPaymentStatusEnum::PAID,
        OrderSchema::EXPIRES_AT => now()->subMinute(),
    ]);

    $this->artisan('order:cancel-expired')->assertSuccessful();

    // paid orders are settled, not abandoned — the refund path decides
    // separately and in V1 does not restore usage
    expect($order->refresh()->{OrderSchema::STATUS})->toBe(OrderStatusEnum::PROCESSING)
        ->and(Redemption::query()->where(RedemptionSchema::ORDER_ID, $order->{OrderSchema::ID})->exists())->toBeTrue()
        ->and((int) $coupon->refresh()->{CouponSchema::USAGE_COUNT})->toBe(1);
});

it('releases the coupon when an admin cancels a still-unpaid order', function () {
    [$order, $coupon] = couponOrder([OrderSchema::EXPIRES_AT => now()->addHour()]);

    $this->actingAs($this->superAdminUser())
        ->patchJson($this->baseUrl('/admin/orders/'.$order->{OrderSchema::ID}), [
            OrderSchema::STATUS => OrderStatusEnum::CANCELLED->value,
        ])
        ->assertOk();

    expect($order->refresh()->{OrderSchema::STATUS})->toBe(OrderStatusEnum::CANCELLED)
        ->and(Redemption::query()->where(RedemptionSchema::ORDER_ID, $order->{OrderSchema::ID})->exists())->toBeFalse()
        ->and((int) $coupon->refresh()->{CouponSchema::USAGE_COUNT})->toBe(0);
});

it('keeps the coupon consumed when an admin refunds a paid order', function () {
    [$order, $coupon] = couponOrder([
        OrderSchema::STATUS => OrderStatusEnum::PROCESSING,
        OrderSchema::PAYMENT_STATUS => OrderPaymentStatusEnum::PAID,
        OrderSchema::EXPIRES_AT => now()->addHour(),
    ]);

    $this->actingAs($this->superAdminUser())
        ->patchJson($this->baseUrl('/admin/orders/'.$order->{OrderSchema::ID}), [
            OrderSchema::PAYMENT_STATUS => OrderPaymentStatusEnum::REFUNDED->value,
        ])
        ->assertOk();

    // V1 business rule: a refunded order genuinely redeemed its coupon
    expect($order->refresh()->{OrderSchema::PAYMENT_STATUS})->toBe(OrderPaymentStatusEnum::REFUNDED)
        ->and(Redemption::query()->where(RedemptionSchema::ORDER_ID, $order->{OrderSchema::ID})->exists())->toBeTrue()
        ->and((int) $coupon->refresh()->{CouponSchema::USAGE_COUNT})->toBe(1);
});
