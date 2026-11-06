<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Modules\Discount\Models\Coupon;
use Modules\Discount\Models\Promotion;
use Modules\Discount\Schemas\Promotion\PromotionSchema;
use Modules\Discount\Tests\Feature\HelperTrait;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

it('exports coupons, promotions and redemptions with redemptions export-only', function () {
    $seeded = $this->seedTransferDiscount();
    $this->actingAs($this->superAdminUser());

    $file = $this->exportedDiscountFile();

    expect($file['module'])->toBe('discount')
        ->and(array_column($file['sections'], 'entity'))->toBe(['coupons', 'promotions', 'redemptions'])
        // Usage state stays behind; configuration travels.
        ->and($file['sections'][0]['rows'][0]['payload']['code'])->toBe('WELCOME10')
        ->and($file['sections'][0]['rows'][0]['payload'])->not->toHaveKey('usage_count')
        // Promotions carry their catalog selections as source ids.
        ->and($file['sections'][1]['rows'][0]['payload'][PromotionSchema::PRODUCT_IDS])->toBe([$seeded['product']->id])
        ->and($file['sections'][1]['rows'][0]['payload'][PromotionSchema::CATEGORY_IDS])->toBe([$seeded['category']->id])
        // Redemptions are history: coupon by code, order/user as source ids.
        ->and($file['sections'][2]['rows'][0]['payload']['coupon_code'])->toBe('WELCOME10')
        ->and($file['sections'][2]['rows'][0]['payload']['order_id'])->toBe(501);

    // The capabilities matrix marks redemptions as export-only.
    $capabilities = collect(
        $this->getJson($this->transferUrl('/admin/capabilities'))->assertOk()->json('data')
    )->firstWhere('module', 'discount');
    $redemptions = collect($capabilities['sections'])->firstWhere('key', 'redemptions');
    expect($redemptions['exportable'])->toBeTrue()
        ->and($redemptions['importable'])->toBeFalse();
});

it('round-trips: wipes discount config and re-imports the export', function () {
    $seeded = $this->seedTransferDiscount();
    $this->actingAs($this->superAdminUser());

    $payload = $this->exportedDiscountFile();

    DB::table('discount_redemptions')->delete();
    DB::table('discount_promotion_products')->delete();
    DB::table('discount_promotion_categories')->delete();
    DB::table('discount_promotions')->delete();
    DB::table('discount_coupons')->delete();

    $response = $this->postJson($this->transferUrl('/admin/imports'), [
        'file' => UploadedFile::fake()->createWithContent('discount.json', json_encode($payload)),
        'on_duplicate' => 'update',
        'dry_run' => false,
    ]);

    $response->assertOk();
    expect($response->json('data.errors'))->toBe([])
        ->and($response->json('data.failed_count'))->toBe(0)
        ->and($response->json('data.created_count'))->toBe(2)
        ->and($response->json('data.skipped_count'))->toBe(1)
        ->and(Coupon::query()->count())->toBe(1)
        ->and(Promotion::query()->count())->toBe(1);

    // The promotion survived with its catalog selections re-attached.
    $promotion = Promotion::query()->first();
    expect($promotion->selectedProductIds()->all())->toBe([$seeded['product']->id])
        ->and($promotion->selectedCategoryIds()->all())->toBe([$seeded['category']->id])
        ->and(Coupon::query()->first()->usage_count)->toBe(0);
});

it('fails promotions referencing products outside the local catalog', function () {
    $this->actingAs($this->superAdminUser());

    $payload = [
        'format' => 'baxela.module-export',
        'module' => 'discount',
        'version' => 1,
        'sections' => [
            ['entity' => 'coupons', 'rows' => []],
            ['entity' => 'promotions', 'rows' => [
                ['source_id' => 1, 'payload' => [
                    'name' => 'Foreign sale', 'scope' => 'specific', 'type' => 'percent',
                    'value' => '5.00', 'priority' => 1, 'is_active' => true,
                    'product_ids' => [999999], 'category_ids' => [],
                ]],
            ]],
            ['entity' => 'redemptions', 'rows' => []],
        ],
    ];

    $response = $this->postJson($this->transferUrl('/admin/imports'), [
        'file' => UploadedFile::fake()->createWithContent('discount.json', json_encode($payload)),
        'on_duplicate' => 'update',
        'dry_run' => false,
    ]);

    $response->assertOk();
    expect($response->json('data.failed_count'))->toBe(1)
        ->and($response->json('data.warnings.0'))->toBe('not_importable:redemptions')
        ->and(Promotion::query()->count())->toBe(0);
});

it('skips existing rows with the skip strategy', function () {
    $this->seedTransferDiscount();
    $this->actingAs($this->superAdminUser());

    $payload = $this->exportedDiscountFile();

    $response = $this->postJson($this->transferUrl('/admin/imports'), [
        'file' => UploadedFile::fake()->createWithContent('discount.json', json_encode($payload)),
        'on_duplicate' => 'skip',
        'dry_run' => false,
    ]);

    $response->assertOk();
    // Coupon + promotion skipped as existing; the export-only redemption
    // row is skipped by the engine.
    expect($response->json('data.skipped_count'))->toBe(3)
        ->and(Coupon::query()->where('code', 'WELCOME10')->count())->toBe(1);
});

it('dry run validates without writing', function () {
    $this->seedTransferDiscount();
    $this->actingAs($this->superAdminUser());

    $payload = $this->exportedDiscountFile();

    DB::table('discount_promotion_products')->delete();
    DB::table('discount_promotion_categories')->delete();
    DB::table('discount_promotions')->delete();
    DB::table('discount_coupons')->delete();

    $response = $this->postJson($this->transferUrl('/admin/imports'), [
        'file' => UploadedFile::fake()->createWithContent('discount.json', json_encode($payload)),
        'on_duplicate' => 'update',
        'dry_run' => true,
    ]);

    $response->assertOk();
    expect($response->json('data.created_count'))->toBe(2)
        ->and(Coupon::query()->count())->toBe(0)
        ->and(Promotion::query()->count())->toBe(0);
});
