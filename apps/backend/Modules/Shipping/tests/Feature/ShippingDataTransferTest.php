<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Shipping\Models\Method;
use Modules\Shipping\Models\Rate;
use Modules\Shipping\Models\Zone;
use Modules\Shipping\Schemas\Rate\RateSchema;
use Modules\Shipping\Tests\Feature\HelperTrait;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

it('exports shipping data through the module endpoint', function () {
    $en = $this->defaultLanguage();
    $seeded = $this->seedTransferShipping((string) $en->id);
    $this->actingAs($this->superAdminUser());

    $file = $this->exportedShippingDataFile();

    expect($file['module'])->toBe('shipping')
        ->and(array_column($file['sections'], 'entity'))->toBe(['zones', 'methods', 'rates'])
        ->and($file['sections'][0]['rows'][0]['payload']['country_codes'])->toBe(['DE'])
        ->and($file['sections'][2]['rows'][0]['payload']['method_id'])->toBe($seeded['method']->id);
});

it('round-trips shipping config via the module endpoint', function () {
    $en = $this->defaultLanguage();
    $this->seedTransferShipping((string) $en->id);
    $this->actingAs($this->superAdminUser());

    $payload = $this->exportedShippingDataFile();

    DB::table('shipping_rates')->delete();
    DB::table('shipping_methods')->delete();
    DB::table('shipping_method_translations')->delete();
    DB::table('shipping_zone_countries')->delete();
    DB::table('shipping_zones')->delete();

    $response = $this->postJson($this->baseUrl('/admin/data/import'), [
        'file' => $this->transferFile(json_encode($payload)),
        'on_duplicate' => 'update',
        'dry_run' => false,
    ]);

    $response->assertOk();
    expect($response->json('data.errors'))->toBe([])
        ->and($response->json('data.created_count'))->toBe(3)
        ->and(Zone::query()->count())->toBe(1)
        ->and(Method::query()->count())->toBe(1)
        ->and(Rate::query()->count())->toBe(1);

    // Everything remapped: zone countries, method translation, rate links.
    $zone = Zone::query()->first();
    $method = Method::query()->first();
    $rate = Rate::query()->first();
    expect($zone->countries()->pluck('code')->all())->toBe(['DE'])
        ->and($rate->{RateSchema::METHOD_ID})->toBe($method->id)
        ->and($rate->{RateSchema::ZONE_ID})->toBe($zone->id);
});

it('denies the data endpoints to guests', function () {
    $this->getJson($this->baseUrl('/admin/data/export'))->assertStatus(401);
    $this->postJson($this->baseUrl('/admin/data/import'), [
        'file' => $this->transferFile('{}'),
        'on_duplicate' => 'update',
        'dry_run' => false,
    ])->assertStatus(401);
});
