<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\Models\User;
use Modules\Tax\Models\TaxClass;
use Modules\Tax\Models\TaxRate;
use Modules\Tax\Schemas\TaxRate\TaxRateSchema;
use Modules\Transfer\Models\TransferImport;
use Modules\Transfer\Tests\Feature\HelperTrait;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

it('round-trips: wipes the tax data and re-imports the export', function () {
    $this->seedCountries('DE', 'US');
    $this->seedTransferTax();
    $this->actingAs($this->superAdminUser());

    $payload = $this->exportedTaxFile();

    TaxRate::query()->delete();
    TaxClass::query()->delete();

    $response = $this->postJson($this->baseUrl('/admin/imports'), [
        'file' => $this->transferFile(json_encode($payload)),
        'on_duplicate' => 'update',
        'dry_run' => false,
    ]);

    $response->assertOk();
    expect($response->json('data.errors'))->toBe([])
        ->and($response->json('data.failed_count'))->toBe(0)
        ->and($response->json('data.created_count'))->toBe(3)
        ->and($response->json('data.sections.tax-classes.created'))->toBe(2)
        ->and($response->json('data.sections.tax-rates.created'))->toBe(1)
        ->and(TaxClass::query()->count())->toBe(2)
        ->and(TaxRate::query()->count())->toBe(1);

    // The rate survived with its class reference remapped to the new ids.
    $rate = TaxRate::query()->first();
    $class = TaxClass::query()->where('name', 'standard')->first();
    expect($rate->{TaxRateSchema::TAX_CLASS_ID})->toBe($class->id)
        ->and((string) $rate->rate)->toBe('19.0000');
});

it('re-imports idempotently with the update strategy', function () {
    $this->seedCountries('DE');
    $this->seedTransferTax();
    $this->actingAs($this->superAdminUser());

    $payload = $this->exportedTaxFile();
    TaxRate::query()->delete();
    TaxClass::query()->delete();

    $this->postJson($this->baseUrl('/admin/imports'), [
        'file' => $this->transferFile(json_encode($payload)),
        'on_duplicate' => 'update',
        'dry_run' => false,
    ])->assertOk();

    $response = $this->postJson($this->baseUrl('/admin/imports'), [
        'file' => $this->transferFile(json_encode($payload)),
        'on_duplicate' => 'update',
        'dry_run' => false,
    ]);

    $response->assertOk();
    expect($response->json('data.created_count'))->toBe(0)
        ->and($response->json('data.updated_count'))->toBe(3)
        ->and(TaxClass::query()->count())->toBe(2)
        ->and(TaxRate::query()->count())->toBe(1);
});

it('skips existing rows with the skip strategy', function () {
    $this->seedCountries('DE');
    $this->seedTransferTax();
    $this->actingAs($this->superAdminUser());

    $payload = $this->exportedTaxFile();
    TaxRate::query()->delete();
    TaxClass::query()->delete();

    $this->postJson($this->baseUrl('/admin/imports'), [
        'file' => $this->transferFile(json_encode($payload)),
        'on_duplicate' => 'skip',
        'dry_run' => false,
    ])->assertOk();

    $response = $this->postJson($this->baseUrl('/admin/imports'), [
        'file' => $this->transferFile(json_encode($payload)),
        'on_duplicate' => 'skip',
        'dry_run' => false,
    ]);

    $response->assertOk();
    expect($response->json('data.skipped_count'))->toBe(3)
        ->and(TaxClass::query()->count())->toBe(2);
});

it('dry run validates without writing', function () {
    $this->seedCountries('DE');
    $this->seedTransferTax();
    $this->actingAs($this->superAdminUser());

    $payload = $this->exportedTaxFile();
    TaxRate::query()->delete();
    TaxClass::query()->delete();
    expect(TaxClass::query()->count())->toBe(0);

    $response = $this->postJson($this->baseUrl('/admin/imports'), [
        'file' => $this->transferFile(json_encode($payload)),
        'on_duplicate' => 'update',
        'dry_run' => true,
    ]);

    $response->assertOk();
    expect($response->json('data.dry_run'))->toBeTrue()
        ->and($response->json('data.created_count'))->toBe(3)
        ->and(TaxClass::query()->count())->toBe(0)
        ->and(TaxRate::query()->count())->toBe(0);
});

it('fails rows referencing an unresolved tax class', function () {
    $this->seedCountries('DE');
    $this->actingAs($this->superAdminUser());

    // Rates without their classes section cannot resolve the reference.
    $payload = [
        'format' => 'baxela.module-export',
        'module' => 'tax',
        'version' => 1,
        'exported_at' => now()->toIso8601String(),
        'sections' => [
            ['entity' => 'tax-rates', 'rows' => [
                ['source_id' => 1, 'payload' => [
                    'name' => 'VAT', 'country_code' => 'DE',
                    'tax_class_id' => 99, 'rate' => '19.0000', 'is_active' => true,
                ]],
            ]],
        ],
    ];

    $response = $this->postJson($this->baseUrl('/admin/imports'), [
        'file' => $this->transferFile(json_encode($payload)),
        'on_duplicate' => 'update',
        'dry_run' => false,
    ]);

    $response->assertOk();
    expect($response->json('data.failed_count'))->toBe(1)
        ->and($response->json('data.errors.0'))->toContain('unresolved_tax_class')
        ->and(TaxRate::query()->count())->toBe(0);
});

it('rejects malformed files with a stable reason', function () {
    $this->actingAs($this->superAdminUser());

    $this->postJson($this->baseUrl('/admin/imports'), [
        'file' => $this->transferFile('{not json'),
        'on_duplicate' => 'update',
        'dry_run' => false,
    ])->assertStatus(422)->assertJsonPath('code', 'transfer.import.failed');

    $this->postJson($this->baseUrl('/admin/imports'), [
        'file' => $this->transferFile(json_encode(['format' => 'other', 'module' => 'tax', 'version' => 1, 'sections' => []])),
        'on_duplicate' => 'update',
        'dry_run' => false,
    ])->assertStatus(422);

    // A file for a module that did not opt in.
    $this->postJson($this->baseUrl('/admin/imports'), [
        'file' => $this->transferFile(json_encode([
            'format' => 'baxela.module-export', 'module' => 'order', 'version' => 1,
            'sections' => [['entity' => 'orders', 'rows' => []]],
        ])),
        'on_duplicate' => 'update',
        'dry_run' => false,
    ])->assertStatus(422);
});

it('previews a file without writing', function () {
    $this->seedCountries('DE');
    $this->seedTransferTax();
    $this->actingAs($this->superAdminUser());

    $payload = $this->exportedTaxFile();

    $response = $this->postJson($this->baseUrl('/admin/imports/preview'), [
        'file' => $this->transferFile(json_encode($payload), 'tax.json'),
    ]);

    $response->assertOk();
    expect($response->json('data.module'))->toBe('tax')
        ->and($response->json('data.total_rows'))->toBe(3)
        ->and($response->json('data.sections.0.entity'))->toBe('tax-classes')
        ->and($response->json('data.sections.0.rows'))->toBe(2)
        ->and($response->json('data.warnings'))->toBe([])
        ->and(TaxClass::query()->count())->toBe(2);
});

it('records the run and exposes it in the history', function () {
    $this->seedCountries('DE');
    $this->seedTransferTax();
    $user = $this->superAdminUser();
    $this->actingAs($user);

    $payload = $this->exportedTaxFile();
    TaxRate::query()->delete();
    TaxClass::query()->delete();

    $this->postJson($this->baseUrl('/admin/imports'), [
        'file' => $this->transferFile(json_encode($payload)),
        'on_duplicate' => 'update',
        'dry_run' => false,
    ])->assertOk();

    expect(TransferImport::query()->count())->toBe(1);
    $record = TransferImport::query()->first();

    $shown = $this->getJson($this->baseUrl('/admin/imports/'.$record->id))->assertOk();
    expect($shown->json('data.module'))->toBe('tax')
        ->and($shown->json('data.status'))->toBe('completed')
        ->and($shown->json('data.user_id'))->toBe($user->id);

    $list = $this->getJson($this->baseUrl('/admin/imports'))->assertOk();
    expect($list->json('data.0.id'))->toBe($record->id);
});

it('honours string booleans from multipart uploads', function () {
    $this->seedCountries('DE');
    $this->seedTransferTax();
    $this->actingAs($this->superAdminUser());

    $payload = $this->exportedTaxFile();

    // A non-JSON (multipart) post delivers every field as a string —
    // the shape the admin panel actually uploads. `(bool) 'false'` is
    // true in PHP, so the flag must be normalized before validation.
    $response = $this->post($this->baseUrl('/admin/imports'), [
        'file' => $this->transferFile(json_encode($payload)),
        'on_duplicate' => 'skip',
        'dry_run' => 'false',
    ]);

    $response->assertOk();
    expect($response->json('data.dry_run'))->toBeFalse()
        ->and($response->json('data.skipped_count'))->toBe(3)
        ->and(TaxClass::query()->count())->toBe(2);
});

it('denies import without permissions', function () {
    $this->actingAs(User::factory()->create(['is_active' => true]));

    $this->postJson($this->baseUrl('/admin/imports'), [
        'file' => $this->transferFile('{}'),
        'on_duplicate' => 'update',
        'dry_run' => false,
    ])->assertStatus(403);
});
