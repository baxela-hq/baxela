<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Country;
use Modules\Core\Models\Currency;
use Modules\Core\Models\Language;
use Modules\Core\Schemas\Currency\CurrencySchema;
use Modules\Core\Schemas\Language\LanguageSchema;
use Modules\Core\Tests\Feature\HelperTransferTrait;

uses(RefreshDatabase::class);
uses(HelperTransferTrait::class);

it('exports the reference data through the module endpoint', function () {
    $this->seedCoreTransferData();
    $this->actingAs($this->superAdminUser());

    $file = $this->exportedCoreFile();

    expect($file['module'])->toBe('core')
        ->and(array_column($file['sections'], 'entity'))->toBe(['languages', 'currencies', 'countries'])
        ->and($file['sections'][0]['rows'])->toHaveCount(2)
        ->and($file['sections'][1]['rows'][0]['payload']['code'])->toBe('USD')
        ->and($file['sections'][2]['rows'][0]['payload']['code'])->toBe('DE');
});

it('round-trips the reference data with the default flags intact', function () {
    $this->seedCoreTransferData();
    $this->actingAs($this->superAdminUser());

    $payload = $this->exportedCoreFile();

    Language::query()->delete();
    Currency::query()->delete();
    Country::query()->delete();

    $response = $this->postJson($this->baseUrl('/admin/data/import'), [
        'file' => $this->transferFile(json_encode($payload)),
        'on_duplicate' => 'update',
        'dry_run' => false,
    ]);

    $response->assertOk();
    expect($response->json('data.errors'))->toBe([])
        ->and($response->json('data.created_count'))->toBe(5)
        ->and(Language::query()->count())->toBe(2)
        ->and(Currency::query()->count())->toBe(1)
        ->and(Country::query()->count())->toBe(2);

    // The imported default language/currency carry the flags; the other
    // rows were demoted.
    expect(Language::query()->where(LanguageSchema::CODE, 'en')->value(LanguageSchema::IS_DEFAULT))->toBeTrue()
        ->and(Language::query()->where(LanguageSchema::CODE, 'de')->value(LanguageSchema::IS_DEFAULT))->toBeFalse()
        ->and(Language::query()->where(LanguageSchema::CODE, 'en')->value(LanguageSchema::IS_ACTIVE))->toBeTrue()
        ->and(Currency::query()->where(CurrencySchema::CODE, 'USD')->value(CurrencySchema::IS_DEFAULT))->toBeTrue();

    // Re-import: the same file updates in place.
    $response = $this->postJson($this->baseUrl('/admin/data/import'), [
        'file' => $this->transferFile(json_encode($payload)),
        'on_duplicate' => 'update',
        'dry_run' => false,
    ]);

    $response->assertOk();
    expect($response->json('data.created_count'))->toBe(0)
        ->and($response->json('data.updated_count'))->toBe(5)
        ->and(Language::query()->count())->toBe(2);
});

it('denies the data endpoints to guests', function () {
    $this->getJson($this->baseUrl('/admin/data/export'))->assertStatus(401);
    $this->postJson($this->baseUrl('/admin/data/import'), [
        'file' => $this->transferFile('{}'),
        'on_duplicate' => 'update',
        'dry_run' => false,
    ])->assertStatus(401);
});
