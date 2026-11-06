<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Modules\Shipping\Models\Method;
use Modules\Shipping\Models\Rate;
use Modules\Shipping\Models\Zone;
use Modules\Shipping\Schemas\Rate\RateSchema;
use Modules\Shipping\Tests\Feature\HelperTrait;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

it('exports zones, methods and rates in dependency order', function () {
    $en = $this->defaultLanguage();
    $seeded = $this->seedTransferShipping((string) $en->id);
    $this->actingAs($this->superAdminUser());

    $file = $this->exportedShippingFile();

    expect($file['module'])->toBe('shipping')
        ->and(array_column($file['sections'], 'entity'))->toBe(['zones', 'methods', 'rates'])
        // Countries travel as codes, translations as language codes.
        ->and($file['sections'][0]['rows'][0]['payload']['country_codes'])->toBe(['DE'])
        ->and($file['sections'][1]['rows'][0]['payload']['code'])->toBe('express')
        ->and($file['sections'][1]['rows'][0]['payload']['translations'][0]['language'])->toBe('en')
        // The rate references both parents by source id.
        ->and($file['sections'][2]['rows'][0]['payload']['method_id'])->toBe($seeded['method']->id)
        ->and($file['sections'][2]['rows'][0]['payload']['zone_id'])->toBe($seeded['zone']->id);
});

it('round-trips: wipes shipping config and re-imports the export', function () {
    $en = $this->defaultLanguage();
    $this->seedTransferShipping((string) $en->id);
    $this->actingAs($this->superAdminUser());

    $payload = $this->exportedShippingFile();

    DB::table('shipping_rates')->delete();
    DB::table('shipping_methods')->delete();
    DB::table('shipping_method_translations')->delete();
    DB::table('shipping_zone_countries')->delete();
    DB::table('shipping_zones')->delete();

    $response = $this->postJson($this->transferUrl('/admin/imports'), [
        'file' => UploadedFile::fake()->createWithContent('shipping.json', json_encode($payload)),
        'on_duplicate' => 'update',
        'dry_run' => false,
    ]);

    $response->assertOk();
    expect($response->json('data.errors'))->toBe([])
        ->and($response->json('data.failed_count'))->toBe(0)
        ->and($response->json('data.created_count'))->toBe(3)
        ->and(Zone::query()->count())->toBe(1)
        ->and(Method::query()->count())->toBe(1)
        ->and(Rate::query()->count())->toBe(1);

    // Everything remapped: zone countries, method translation, rate links.
    $zone = Zone::query()->first();
    $method = Method::query()->first();
    $rate = Rate::query()->first();
    expect($zone->countries()->pluck('code')->all())->toBe(['DE'])
        ->and($method->translations()->first()->name)->toBe('Express')
        ->and($rate->{RateSchema::METHOD_ID})->toBe($method->id)
        ->and($rate->{RateSchema::ZONE_ID})->toBe($zone->id)
        ->and((string) $rate->price)->toBe('9.99');
});

it('fails rates whose method or zone reference cannot resolve', function () {
    $this->actingAs($this->superAdminUser());

    $payload = [
        'format' => 'baxela.module-export',
        'module' => 'shipping',
        'version' => 1,
        'sections' => [
            ['entity' => 'zones', 'rows' => []],
            ['entity' => 'methods', 'rows' => []],
            ['entity' => 'rates', 'rows' => [
                ['source_id' => 1, 'payload' => ['method_id' => 77, 'zone_id' => 88, 'price' => '5.00']],
            ]],
        ],
    ];

    $response = $this->postJson($this->transferUrl('/admin/imports'), [
        'file' => UploadedFile::fake()->createWithContent('shipping.json', json_encode($payload)),
        'on_duplicate' => 'update',
        'dry_run' => false,
    ]);

    $response->assertOk();
    expect($response->json('data.failed_count'))->toBe(1)
        ->and($response->json('data.errors.0'))->toContain('unresolved_method')
        ->and(Rate::query()->count())->toBe(0);
});

it('skips existing shipping rows with the skip strategy', function () {
    $en = $this->defaultLanguage();
    $this->seedTransferShipping((string) $en->id);
    $this->actingAs($this->superAdminUser());

    $payload = $this->exportedShippingFile();

    $response = $this->postJson($this->transferUrl('/admin/imports'), [
        'file' => UploadedFile::fake()->createWithContent('shipping.json', json_encode($payload)),
        'on_duplicate' => 'skip',
        'dry_run' => false,
    ]);

    $response->assertOk();
    expect($response->json('data.skipped_count'))->toBe(3)
        ->and(Method::query()->where('code', 'express')->count())->toBe(1);
});

it('dry run validates without writing', function () {
    $en = $this->defaultLanguage();
    $this->seedTransferShipping((string) $en->id);
    $this->actingAs($this->superAdminUser());

    $payload = $this->exportedShippingFile();

    DB::table('shipping_rates')->delete();
    DB::table('shipping_methods')->delete();
    DB::table('shipping_method_translations')->delete();
    DB::table('shipping_zone_countries')->delete();
    DB::table('shipping_zones')->delete();

    $response = $this->postJson($this->transferUrl('/admin/imports'), [
        'file' => UploadedFile::fake()->createWithContent('shipping.json', json_encode($payload)),
        'on_duplicate' => 'update',
        'dry_run' => true,
    ]);

    $response->assertOk();
    expect($response->json('data.created_count'))->toBe(3)
        ->and(Zone::query()->count())->toBe(0)
        ->and(Method::query()->count())->toBe(0)
        ->and(Rate::query()->count())->toBe(0);
});

it('re-imports idempotently with the update strategy', function () {
    $en = $this->defaultLanguage();
    $this->seedTransferShipping((string) $en->id);
    $this->actingAs($this->superAdminUser());

    $payload = $this->exportedShippingFile();

    $this->postJson($this->transferUrl('/admin/imports'), [
        'file' => UploadedFile::fake()->createWithContent('shipping.json', json_encode($payload)),
        'on_duplicate' => 'update',
        'dry_run' => false,
    ])->assertOk();

    $response = $this->postJson($this->transferUrl('/admin/imports'), [
        'file' => UploadedFile::fake()->createWithContent('shipping.json', json_encode($payload)),
        'on_duplicate' => 'update',
        'dry_run' => false,
    ]);

    $response->assertOk();
    expect($response->json('data.created_count'))->toBe(0)
        ->and($response->json('data.updated_count'))->toBe(3)
        ->and(Method::query()->count())->toBe(1)
        ->and(Rate::query()->count())->toBe(1);
});
