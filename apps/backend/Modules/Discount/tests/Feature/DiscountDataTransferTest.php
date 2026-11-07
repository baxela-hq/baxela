<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Discount\Models\Coupon;
use Modules\Discount\Models\Promotion;
use Modules\Discount\Schemas\Promotion\PromotionSchema;
use Modules\Discount\Tests\Feature\HelperTrait;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

it('exports discount data through the module endpoint', function () {
    $seeded = $this->seedTransferDiscount();
    $this->actingAs($this->superAdminUser());

    $file = $this->exportedDiscountDataFile();

    expect($file['module'])->toBe('discount')
        ->and(array_column($file['sections'], 'entity'))->toBe(['coupons', 'promotions', 'redemptions'])
        ->and($file['sections'][0]['rows'][0]['payload']['code'])->toBe('WELCOME10')
        ->and($file['sections'][1]['rows'][0]['payload'][PromotionSchema::PRODUCT_IDS])->toBe([$seeded['product']->id])
        ->and($file['sections'][2]['rows'][0]['payload']['coupon_code'])->toBe('WELCOME10');
});

it('round-trips discount config via the module endpoint', function () {
    $seeded = $this->seedTransferDiscount();
    $this->actingAs($this->superAdminUser());

    $payload = $this->exportedDiscountDataFile();

    DB::table('discount_redemptions')->delete();
    DB::table('discount_promotion_products')->delete();
    DB::table('discount_promotion_categories')->delete();
    DB::table('discount_promotions')->delete();
    DB::table('discount_coupons')->delete();

    $response = $this->postJson($this->baseUrl('/admin/data/import'), [
        'file' => $this->transferFile(json_encode($payload)),
        'on_duplicate' => 'update',
        'dry_run' => false,
    ]);

    $response->assertOk();
    expect($response->json('data.errors'))->toBe([])
        ->and($response->json('data.created_count'))->toBe(2)
        ->and($response->json('data.skipped_count'))->toBe(1)
        ->and(Coupon::query()->count())->toBe(1)
        ->and(Promotion::query()->count())->toBe(1);

    // The promotion survived with its catalog selections re-attached.
    $promotion = Promotion::query()->first();
    expect($promotion->selectedProductIds()->all())->toBe([$seeded['product']->id])
        ->and($promotion->selectedCategoryIds()->all())->toBe([$seeded['category']->id]);
});

it('denies the data endpoints to guests', function () {
    $this->getJson($this->baseUrl('/admin/data/export'))->assertStatus(401);
    $this->postJson($this->baseUrl('/admin/data/import'), [
        'file' => $this->transferFile('{}'),
        'on_duplicate' => 'update',
        'dry_run' => false,
    ])->assertStatus(401);
});
