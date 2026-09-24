<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\Gateways\Discount\RedemptionResult;
use Modules\Discount\Gateways\DiscountGateway;
use Modules\Discount\Models\Coupon;
use Modules\Discount\Models\Redemption;
use Modules\Discount\Schemas\Coupon\CouponSchema;
use Modules\Discount\Schemas\Redemption\RedemptionSchema;

uses(RefreshDatabase::class);

function couponForRedemption(array $overrides = []): Coupon
{
    return Coupon::factory()->create($overrides);
}

function recordFor(Coupon $coupon, int $orderId, int $userId = 1): RedemptionResult
{
    return app(DiscountGateway::class)->recordRedemption((int) $coupon->id, $orderId, $userId, 2000);
}

function releaseFor(int $orderId): bool
{
    return app(DiscountGateway::class)->releaseRedemption($orderId);
}

it('records a redemption and increments the counter under the lock', function () {
    $coupon = couponForRedemption();

    $result = DB::transaction(fn () => recordFor($coupon, 101, 7));

    expect($result)->toBe(RedemptionResult::REDEEMED);

    $redemption = Redemption::query()->where(RedemptionSchema::ORDER_ID, 101)->first();
    expect($redemption)->not->toBeNull()
        ->and((int) $redemption->{RedemptionSchema::COUPON_ID})->toBe((int) $coupon->id)
        ->and((int) $redemption->{RedemptionSchema::USER_ID})->toBe(7)
        // discount stored as a major-unit decimal snapshot (20.00)
        ->and((float) $redemption->{RedemptionSchema::DISCOUNT_AMOUNT})->toBe(20.0)
        ->and((int) $coupon->refresh()->{CouponSchema::USAGE_COUNT})->toBe(1);
});

it('re-verifies the global limit authoritatively (usage_limit = 1)', function () {
    $coupon = couponForRedemption([CouponSchema::USAGE_LIMIT => 1, CouponSchema::USAGE_COUNT => 1]);

    $result = DB::transaction(fn () => recordFor($coupon, 102));

    expect($result)->toBe(RedemptionResult::GLOBAL_LIMIT_REACHED)
        ->and(Redemption::query()->where(RedemptionSchema::ORDER_ID, 102)->exists())->toBeFalse()
        ->and((int) $coupon->refresh()->{CouponSchema::USAGE_COUNT})->toBe(1);
});

it('re-verifies the per-user limit authoritatively (per_user_limit = 1)', function () {
    $coupon = couponForRedemption([CouponSchema::PER_USER_LIMIT => 1]);

    $first = DB::transaction(fn () => recordFor($coupon, 103, 9));
    $second = DB::transaction(fn () => recordFor($coupon, 104, 9));
    $otherUser = DB::transaction(fn () => recordFor($coupon, 105, 10));

    expect($first)->toBe(RedemptionResult::REDEEMED)
        ->and($second)->toBe(RedemptionResult::PER_USER_LIMIT_REACHED)
        ->and(Redemption::query()->where(RedemptionSchema::ORDER_ID, 104)->exists())->toBeFalse()
        // a different user is unaffected
        ->and($otherUser)->toBe(RedemptionResult::REDEEMED)
        ->and((int) $coupon->refresh()->{CouponSchema::USAGE_COUNT})->toBe(2);
});

it('keeps usage_count equal to retained redemptions across record → release → record', function () {
    $coupon = couponForRedemption();

    DB::transaction(fn () => recordFor($coupon, 201));
    DB::transaction(fn () => releaseFor(201));
    DB::transaction(fn () => recordFor($coupon, 202));

    $retained = Redemption::query()->where(RedemptionSchema::COUPON_ID, $coupon->id)->count();

    expect($retained)->toBe(1)
        ->and((int) $coupon->refresh()->{CouponSchema::USAGE_COUNT})->toBe(1);
});

it('releases idempotently — a second release never double-decrements', function () {
    $coupon = couponForRedemption();

    DB::transaction(fn () => recordFor($coupon, 301));

    $first = DB::transaction(fn () => releaseFor(301));
    $second = DB::transaction(fn () => releaseFor(301));

    expect($first)->toBeTrue()
        ->and($second)->toBeFalse()
        ->and((int) $coupon->refresh()->{CouponSchema::USAGE_COUNT})->toBe(0)
        ->and(Redemption::query()->where(RedemptionSchema::ORDER_ID, 301)->exists())->toBeFalse();
});

it('reports no release for an order without a redemption', function () {
    expect(DB::transaction(fn () => releaseFor(9999)))->toBeFalse();
});

it('rolls the redemption and counter back with the caller transaction', function () {
    $coupon = couponForRedemption();

    try {
        DB::transaction(function () use ($coupon): void {
            recordFor($coupon, 401);
            // a later step in the caller's flow fails — everything above
            // must roll back together (recordRedemption never commits
            // independently)
            throw new RuntimeException('later step failed');
        });
    } catch (RuntimeException) {
    }

    expect(Redemption::query()->where(RedemptionSchema::ORDER_ID, 401)->exists())->toBeFalse()
        ->and((int) $coupon->refresh()->{CouponSchema::USAGE_COUNT})->toBe(0);
});
