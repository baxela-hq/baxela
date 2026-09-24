<?php

use Modules\Discount\Gateways\DiscountGateway;
use Modules\Discount\Models\Coupon;
use Modules\Discount\Models\Redemption;
use Modules\Discount\Schemas\Coupon\CouponSchema;

/*
 * No RefreshDatabase here on purpose: it wraps every test in a transaction,
 * which would make the "no open transaction" guard untestable. Migrations
 * run explicitly instead, leaving transactionLevel() at zero.
 */
it('refuses redemption mutations outside an open transaction', function () {
    $this->artisan('migrate:fresh');

    $coupon = Coupon::factory()->create();
    $gateway = app(DiscountGateway::class);

    expect(fn () => $gateway->recordRedemption((int) $coupon->id, 1, 1, 1000))
        ->toThrow(RuntimeException::class)
        ->and(fn () => $gateway->releaseRedemption(1))
        ->toThrow(RuntimeException::class)
        // and nothing was written
        ->and(Redemption::count())->toBe(0)
        ->and((int) $coupon->refresh()->{CouponSchema::USAGE_COUNT})->toBe(0);
});
