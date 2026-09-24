<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\Models\User;
use Modules\Discount\Models\Coupon;
use Modules\Discount\Models\Redemption;
use Modules\Discount\Schemas\Coupon\CouponSchema;
use Modules\Discount\Schemas\Redemption\RedemptionSchema;
use Modules\Discount\Tests\Feature\HelperTrait;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

function couponPayload(array $overrides = []): array
{
    return array_merge([
        CouponSchema::CODE => 'SAVE20',
        CouponSchema::NAME => 'Spring sale',
        CouponSchema::TYPE => 'percent',
        CouponSchema::VALUE => 20,
        CouponSchema::MAX_DISCOUNT_AMOUNT => null,
        CouponSchema::MIN_ORDER_AMOUNT => null,
        CouponSchema::STARTS_AT => null,
        CouponSchema::ENDS_AT => null,
        CouponSchema::USAGE_LIMIT => null,
        CouponSchema::PER_USER_LIMIT => null,
        CouponSchema::IS_ACTIVE => true,
    ], $overrides);
}

it('lists coupons for a super admin', function () {
    Coupon::factory()->count(3)->sequence(
        [CouponSchema::CODE => 'ALPHA'],
        [CouponSchema::CODE => 'BRAVO'],
        [CouponSchema::CODE => 'CHARLIE'],
    )->create();

    $this->actingAs($this->superAdminUser())
        ->getJson($this->baseUrl('/admin/coupons'))
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('data.0.'.CouponSchema::CODE, 'CHARLIE');
});

it('creates a coupon, normalizing the code to uppercase', function () {
    $this->actingAs($this->superAdminUser())
        ->postJson($this->baseUrl('/admin/coupons'), couponPayload([
            CouponSchema::CODE => ' save20 ',
            CouponSchema::TYPE => 'fixed',
            CouponSchema::VALUE => 30,
            CouponSchema::USAGE_LIMIT => 100,
            CouponSchema::PER_USER_LIMIT => 1,
            CouponSchema::STARTS_AT => '2026-10-01T00:00:00Z',
            CouponSchema::ENDS_AT => '2026-10-31T23:59:59Z',
        ]))
        ->assertCreated()
        ->assertJsonPath('data.'.CouponSchema::CODE, 'SAVE20')
        ->assertJsonPath('data.'.CouponSchema::USAGE_COUNT, 0);

    expect(Coupon::query()->where(CouponSchema::CODE, 'SAVE20')->exists())->toBeTrue();
});

it('shows and updates a coupon', function () {
    $coupon = Coupon::factory()->create([CouponSchema::CODE => 'OLD']);

    $this->actingAs($this->superAdminUser())
        ->getJson($this->baseUrl('/admin/coupons/'.$coupon->id))
        ->assertOk()
        ->assertJsonPath('data.'.CouponSchema::CODE, 'OLD');

    $this->actingAs($this->superAdminUser())
        ->patchJson($this->baseUrl('/admin/coupons/'.$coupon->id), couponPayload([
            CouponSchema::CODE => 'NEW',
            CouponSchema::IS_ACTIVE => false,
        ]))
        ->assertOk()
        ->assertJsonPath('data.'.CouponSchema::CODE, 'NEW');

    expect($coupon->refresh())
        ->{CouponSchema::CODE}->toBe('NEW')
        ->{CouponSchema::IS_ACTIVE}->toBeFalse();
});

it('deletes a coupon that was never redeemed', function () {
    $coupon = Coupon::factory()->create();

    $this->actingAs($this->superAdminUser())
        ->deleteJson($this->baseUrl('/admin/coupons/'.$coupon->id))
        ->assertNoContent();

    expect(Coupon::count())->toBe(0);
});

it('refuses deletion when redemption history exists, even after full release', function () {
    $coupon = Coupon::factory()->create([CouponSchema::USAGE_COUNT => 0]);

    // Released redemption: the row remains as history while the counter
    // went back to zero — deletion must still be refused
    Redemption::query()->create([
        RedemptionSchema::COUPON_ID => $coupon->id,
        RedemptionSchema::ORDER_ID => 42,
        RedemptionSchema::USER_ID => 7,
        RedemptionSchema::DISCOUNT_AMOUNT => 10,
    ]);

    $this->actingAs($this->superAdminUser())
        ->deleteJson($this->baseUrl('/admin/coupons/'.$coupon->id))
        ->assertStatus(400)
        ->assertJsonPath('code', 'discount.coupon.has_redemptions');

    expect(Coupon::count())->toBe(1);
});

it('rejects invalid coupon configurations', function (array $overrides) {
    Coupon::factory()->create([CouponSchema::CODE => 'TAKEN']);

    $this->actingAs($this->superAdminUser())
        ->postJson($this->baseUrl('/admin/coupons'), couponPayload($overrides))
        ->assertStatus(422);
})->with([
    'percent above 100' => [[CouponSchema::VALUE => 150]],
    'zero value' => [[CouponSchema::VALUE => 0]],
    'duplicate code' => [[CouponSchema::CODE => 'taken']],
    'cap on a fixed coupon' => [[CouponSchema::TYPE => 'fixed', CouponSchema::VALUE => 10, CouponSchema::MAX_DISCOUNT_AMOUNT => 5]],
    'ends before start' => [[CouponSchema::STARTS_AT => '2026-10-10T00:00:00Z', CouponSchema::ENDS_AT => '2026-10-01T00:00:00Z']],
    'zero usage limit' => [[CouponSchema::USAGE_LIMIT => 0]],
    'zero min order' => [[CouponSchema::MIN_ORDER_AMOUNT => 0]],
    'bad code characters' => [[CouponSchema::CODE => 'not valid!']],
]);

it('denies coupon management without admin permission', function () {
    $this->actingAs(User::factory()->create())
        ->getJson($this->baseUrl('/admin/coupons'))
        ->assertStatus(403);
});
