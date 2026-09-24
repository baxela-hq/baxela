<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Discount\Gateways\DiscountGateway;
use Modules\Discount\Models\Coupon;
use Modules\Discount\Models\Redemption;
use Modules\Discount\Schemas\Coupon\CouponSchema;
use Modules\Discount\Schemas\Redemption\RedemptionSchema;

uses(RefreshDatabase::class);

function evaluateCoupon(array $couponOverrides = [], int $subtotalMinor = 20000, int $userId = 1)
{
    $coupon = Coupon::factory()->create($couponOverrides);

    return [
        app(DiscountGateway::class)->evaluate($coupon->{CouponSchema::CODE}, $subtotalMinor, $userId),
        $coupon,
    ];
}

it('computes a percent discount and rounds half-up once', function () {
    // 100.01 × 15% = 15.0015 → 15.00
    [$evaluation] = evaluateCoupon([CouponSchema::TYPE => 'percent', CouponSchema::VALUE => 15], 10001);

    expect($evaluation->isEligible())->toBeTrue()
        ->and($evaluation->discount->discount_minor)->toBe(1500)
        ->and($evaluation->discount->type)->toBe('percent');
});

it('computes a fixed discount', function () {
    [$evaluation] = evaluateCoupon([CouponSchema::TYPE => 'fixed', CouponSchema::VALUE => 20]);

    expect($evaluation->isEligible())->toBeTrue()
        ->and($evaluation->discount->discount_minor)->toBe(2000);
});

it('caps a percent discount at the optional max amount', function () {
    // 15% of 500.00 = 75.00, capped at 50.00
    [$evaluation] = evaluateCoupon([
        CouponSchema::TYPE => 'percent',
        CouponSchema::VALUE => 15,
        CouponSchema::MAX_DISCOUNT_AMOUNT => 50,
    ], 50000);

    expect($evaluation->discount->discount_minor)->toBe(5000);
});

it('clamps the discount to the subtotal so totals never go negative', function () {
    // 100 off a 30 subtotal → 30
    [$evaluation] = evaluateCoupon([CouponSchema::TYPE => 'fixed', CouponSchema::VALUE => 100], 3000);

    expect($evaluation->discount->discount_minor)->toBe(3000);
});

it('treats a coupon that rounds to zero as eligible', function () {
    // 0.01 × 1% = 0.0001 → 0.00; eligibility and amount are separate concepts
    [$evaluation] = evaluateCoupon([CouponSchema::TYPE => 'percent', CouponSchema::VALUE => 1], 1);

    expect($evaluation->isEligible())->toBeTrue()
        ->and($evaluation->discount->discount_minor)->toBe(0);
});

it('matches codes case-insensitively', function () {
    $coupon = Coupon::factory()->create([CouponSchema::CODE => 'SAVE20']);

    $evaluation = app(DiscountGateway::class)->evaluate('save20', 20000, 1);

    expect($evaluation->isEligible())->toBeTrue()
        ->and($evaluation->discount->coupon_id)->toBe((int) $coupon->id);
});

it('reports each ineligibility reason distinctly', function (array $overrides, string $failure) {
    [$evaluation] = evaluateCoupon($overrides);

    expect($evaluation->isEligible())->toBeFalse()
        ->and($evaluation->failure->value)->toBe($failure);
})->with([
    'inactive' => [[CouponSchema::IS_ACTIVE => false], 'inactive'],
    'not started' => [[CouponSchema::STARTS_AT => now()->addDay()], 'not_started'],
    'expired' => [[CouponSchema::ENDS_AT => now()->subDay()], 'expired'],
    'global usage limit' => [[CouponSchema::USAGE_LIMIT => 1, CouponSchema::USAGE_COUNT => 1], 'usage_limit'],
    'min order amount' => [[CouponSchema::MIN_ORDER_AMOUNT => 300], 'min_order_amount'],
]);

it('reports the per-user limit using retained redemption rows', function () {
    $coupon = Coupon::factory()->create([CouponSchema::PER_USER_LIMIT => 1]);
    Redemption::query()->create([
        RedemptionSchema::COUPON_ID => $coupon->id,
        RedemptionSchema::ORDER_ID => 11,
        RedemptionSchema::USER_ID => 5,
        RedemptionSchema::DISCOUNT_AMOUNT => 10,
    ]);

    $blocked = app(DiscountGateway::class)->evaluate($coupon->{CouponSchema::CODE}, 20000, 5);
    $otherUser = app(DiscountGateway::class)->evaluate($coupon->{CouponSchema::CODE}, 20000, 6);

    expect($blocked->isEligible())->toBeFalse()
        ->and($blocked->failure->value)->toBe('per_user_limit')
        ->and($otherUser->isEligible())->toBeTrue();
});

it('fails evaluation for an unknown code', function () {
    $evaluation = app(DiscountGateway::class)->evaluate('NOPE', 20000, 1);

    expect($evaluation->isEligible())->toBeFalse()
        ->and($evaluation->failure->value)->toBe('not_found');
});
