<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Modules\Core\Models\Currency;
use Modules\Core\Models\Language;
use Modules\Core\Schemas\Currency\CurrencySchema;
use Modules\Setting\Models\Setting;
use Modules\Setting\Tests\Feature\HelperTrait;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

it('exports settings through the module endpoint with portable references', function () {
    $en = $this->defaultLanguage();
    $currency = Currency::factory()->create([CurrencySchema::IS_DEFAULT => true]);
    $this->seedTransferSettings((int) $en->id, (string) $currency->id);
    $this->actingAs($this->superAdminUser());

    $file = $this->exportedSettingDataFile();

    expect($file['module'])->toBe('setting')
        ->and(array_column($file['sections'], 'entity'))->toBe(['settings']);

    $rows = collect($file['sections'][0]['rows']);
    $languageRow = $rows->first(fn ($row) => $row['payload']['name'] === 'language_id');
    expect($languageRow['payload']['value'])->toBe('en');
});

it('round-trips settings via the module endpoint', function () {
    $en = $this->defaultLanguage();
    $currency = Currency::factory()->create([CurrencySchema::IS_DEFAULT => true]);
    $this->seedTransferSettings((int) $en->id, (string) $currency->id);
    $this->actingAs($this->superAdminUser());

    $payload = $this->exportedSettingDataFile();

    Setting::query()->delete();
    // Fresh-store state: the seeder rows exist, values untouched.
    Artisan::call('db:seed', ['--class' => 'Modules\Setting\Database\Seeders\SettingDatabaseSeeder']);

    $response = $this->postJson($this->baseUrl('/admin/data/import'), [
        'file' => $this->transferFile(json_encode($payload)),
        'on_duplicate' => 'update',
        'dry_run' => false,
    ]);

    $response->assertOk();
    expect($response->json('data.module'))->toBe('setting')
        ->and($response->json('data.errors'))->toBe([])
        ->and($response->json('data.updated_count'))->toBe(3)
        ->and(Setting::query()->where('name', 'website_title')->value('value'))->toBe('Old Shop')
        // The id-reference setting re-resolved through the language code.
        ->and(Setting::query()->where('name', 'language_id')->value('value'))->toBe((string) $en->id);
});

it('denies the data endpoints to guests', function () {
    $this->getJson($this->baseUrl('/admin/data/export'))->assertStatus(401);
    $this->postJson($this->baseUrl('/admin/data/import'), [
        'file' => $this->transferFile('{}'),
        'on_duplicate' => 'update',
        'dry_run' => false,
    ])->assertStatus(401);
});
