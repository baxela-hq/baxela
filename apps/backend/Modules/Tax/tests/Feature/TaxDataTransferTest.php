<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\TransferImport;
use Modules\Tax\Models\TaxClass;
use Modules\Tax\Models\TaxRate;
use Modules\Tax\Schemas\TaxRate\TaxRateSchema;
use Modules\Tax\Tests\Feature\HelperTrait;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

it('exports tax data through the module endpoint as a json attachment', function () {
    $this->seedCountries('DE');
    $seeded = $this->seedTransferTax();
    $this->actingAs($this->superAdminUser());

    $response = $this->getJson($this->baseUrl('/admin/data/export'));
    $file = json_decode($response->streamedContent(), true);

    expect($file['format'])->toBe('baxela.module-export')
        ->and($file['module'])->toBe('tax')
        ->and(array_column($file['sections'], 'entity'))->toBe(['tax-classes', 'tax-rates'])
        ->and($file['sections'][1]['rows'][0]['payload']['tax_class_id'])->toBe($seeded['default']->id)
        ->and($response->headers->get('Content-Disposition'))->toContain('attachment; filename=transfer-tax-')
        ->and($response->headers->get('Content-Type'))->toBe('application/json; charset=UTF-8');
});

it('previews an uploaded file through the module endpoint', function () {
    $this->seedCountries('DE');
    $this->seedTransferTax();
    $this->actingAs($this->superAdminUser());

    $payload = $this->exportedTaxFile();

    $response = $this->postJson($this->baseUrl('/admin/data/import/preview'), [
        'file' => $this->transferFile(json_encode($payload), 'tax.json'),
    ]);

    $response->assertOk();
    expect($response->json('data.module'))->toBe('tax')
        ->and($response->json('data.total_rows'))->toBe(3)
        ->and($response->json('data.sections.0.rows'))->toBe(2);
});

it('round-trips: wipes tax data and re-imports via the module endpoint', function () {
    $this->seedCountries('DE');
    $this->seedTransferTax();
    $this->actingAs($this->superAdminUser());

    $payload = $this->exportedTaxFile();

    TaxRate::query()->delete();
    TaxClass::query()->delete();

    $response = $this->postJson($this->baseUrl('/admin/data/import'), [
        'file' => $this->transferFile(json_encode($payload)),
        'on_duplicate' => 'update',
        'dry_run' => false,
    ]);

    $response->assertOk();
    expect($response->json('data.module'))->toBe('tax')
        ->and($response->json('data.errors'))->toBe([])
        ->and($response->json('data.created_count'))->toBe(3)
        ->and(TaxClass::query()->count())->toBe(2)
        ->and(TaxRate::query()->count())->toBe(1);

    // The rate's class reference was remapped to the recreated class.
    $class = TaxClass::query()->where('name', 'standard')->first();
    expect(TaxRate::query()->first()->{TaxRateSchema::TAX_CLASS_ID})->toBe($class->id);
});

it('rejects a transfer file that declares another module', function () {
    $this->actingAs($this->superAdminUser());

    $payload = [
        'format' => 'baxela.module-export',
        'module' => 'setting',
        'version' => 1,
        'sections' => [['entity' => 'settings', 'rows' => []]],
    ];

    $this->postJson($this->baseUrl('/admin/data/import'), [
        'file' => $this->transferFile(json_encode($payload)),
        'on_duplicate' => 'update',
        'dry_run' => false,
    ])->assertStatus(422);
});

it('rejects malformed files with stable reasons', function () {
    $this->actingAs($this->superAdminUser());

    $this->postJson($this->baseUrl('/admin/data/import'), [
        'file' => $this->transferFile('{not json'),
        'on_duplicate' => 'update',
        'dry_run' => false,
    ])->assertStatus(422)->assertJsonPath('code', 'transfer.import.failed')
        ->assertJsonPath('meta.reason', 'invalid_json');

    $this->postJson($this->baseUrl('/admin/data/import'), [
        'file' => $this->transferFile(json_encode([
            'format' => 'other', 'module' => 'tax', 'version' => 1, 'sections' => [],
        ])),
        'on_duplicate' => 'update',
        'dry_run' => false,
    ])->assertStatus(422)->assertJsonPath('meta.reason', 'unknown_format');
});

it('skips existing rows with the skip strategy', function () {
    $this->seedCountries('DE');
    $this->seedTransferTax();
    $this->actingAs($this->superAdminUser());

    $payload = $this->exportedTaxFile();

    $response = $this->postJson($this->baseUrl('/admin/data/import'), [
        'file' => $this->transferFile(json_encode($payload)),
        'on_duplicate' => 'skip',
        'dry_run' => false,
    ]);

    $response->assertOk();
    expect($response->json('data.skipped_count'))->toBe(3)
        ->and(TaxClass::query()->count())->toBe(2)
        ->and(TaxRate::query()->count())->toBe(1);
});

it('scopes the module history endpoint to tax runs', function () {
    $this->seedCountries('DE');
    $this->seedTransferTax();
    $this->actingAs($this->superAdminUser());

    $payload = $this->exportedTaxFile();
    TaxRate::query()->delete();
    TaxClass::query()->delete();

    $this->postJson($this->baseUrl('/admin/data/import'), [
        'file' => $this->transferFile(json_encode($payload)),
        'on_duplicate' => 'update',
        'dry_run' => false,
    ])->assertOk();

    $list = $this->getJson($this->baseUrl('/admin/data/imports'))->assertOk();
    $modules = collect($list->json('data'))->pluck('module')->unique()->values()->all();
    $total = TransferImport::query()->count();

    expect($modules)->toBe(['tax'])
        ->and($list->json('meta.total'))->toBe($total);
});

it('denies the data endpoints to guests', function () {
    $this->getJson($this->baseUrl('/admin/data/export'))->assertStatus(401);
    $this->postJson($this->baseUrl('/admin/data/import'), [
        'file' => $this->transferFile('{}'),
        'on_duplicate' => 'update',
        'dry_run' => false,
    ])->assertStatus(401);
});
