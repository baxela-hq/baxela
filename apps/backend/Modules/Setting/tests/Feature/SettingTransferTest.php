<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Modules\Core\Models\Currency;
use Modules\Core\Models\Language;
use Modules\Core\Schemas\Currency\CurrencySchema;
use Modules\Core\Schemas\Language\LanguageSchema;
use Modules\Setting\Models\Setting;
use Modules\Setting\Tests\Feature\HelperTrait;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

it('exports settings with language codes and code-shaped id references', function () {
    $en = $this->defaultLanguage();
    $currency = Currency::factory()->create([CurrencySchema::IS_DEFAULT => true]);
    $this->seedTransferSettings((int) $en->id, (string) $currency->id);
    $this->actingAs($this->superAdminUser());

    $file = json_decode(
        $this->getJson($this->transferUrl('/admin/exports/setting'))->assertOk()->streamedContent(),
        true
    );

    expect($file['module'])->toBe('setting')
        ->and(array_column($file['sections'], 'entity'))->toBe(['settings']);

    $rows = collect($file['sections'][0]['rows']);

    // The id-reference settings travel as codes, not store ids.
    $languageRow = $rows->first(fn ($row) => $row['payload']['name'] === 'language_id');
    $currencyRow = $rows->first(fn ($row) => $row['payload']['name'] === 'currency_id');
    expect($languageRow['payload']['value'])->toBe('en')
        ->and($currencyRow['payload']['value'])->toBe($currency->{CurrencySchema::CODE});

    // Translations carry language codes.
    $titleRow = $rows->first(fn ($row) => $row['payload']['name'] === 'website_title');
    expect($titleRow['payload']['translations'])->toBe([
        ['language' => 'en', 'value' => 'Old Shop EN'],
    ]);
});

it('round-trips settings and re-syncs the core default-language flag', function () {
    $en = $this->defaultLanguage();
    $currency = Currency::factory()->create([CurrencySchema::IS_DEFAULT => true]);
    $this->seedTransferSettings((int) $en->id, (string) $currency->id);
    $this->actingAs($this->superAdminUser());

    $payload = json_decode(
        $this->getJson($this->transferUrl('/admin/exports/setting'))->assertOk()->streamedContent(),
        true
    );

    Setting::query()->delete();
    expect(Setting::query()->count())->toBe(0);

    // A drifted importing store: settings re-seeded (fresh store state),
    // and the default flag moved away from `en`.
    $this->artisan('db:seed', ['--class' => 'Modules\Setting\Database\Seeders\SettingDatabaseSeeder']);
    $fa = Language::factory()->create([LanguageSchema::CODE => 'fa', LanguageSchema::IS_ACTIVE => true, LanguageSchema::IS_DEFAULT => true]);
    $en->refresh()->update([LanguageSchema::IS_DEFAULT => false]);

    $response = $this->postJson($this->transferUrl('/admin/imports'), [
        'file' => UploadedFile::fake()->createWithContent('setting.json', json_encode($payload)),
        'on_duplicate' => 'update',
        'dry_run' => false,
    ]);

    $response->assertOk();
    expect($response->json('data.errors'))->toBe([])
        ->and($response->json('data.failed_count'))->toBe(0)
        ->and($response->json('data.updated_count'))->toBe(3)
        ->and(Setting::query()->count())->toBe(8);

    $title = Setting::query()->where('name', 'website_title')->first();
    expect($title->value)->toBe('Old Shop')
        ->and($title->translations()->where('language_id', $en->id)->value('value'))->toBe('Old Shop EN')
        // The import re-points the default language and mirrors it onto core.
        ->and($title->translations()->where('language_id', $fa->id)->exists())->toBeFalse();

    $languageSetting = Setting::query()->where('name', 'language_id')->first();
    expect($languageSetting->value)->toBe((string) $en->id)
        ->and($en->refresh()->{LanguageSchema::IS_DEFAULT})->toBeTrue()
        ->and($fa->refresh()->{LanguageSchema::IS_DEFAULT})->toBeFalse();
});

it('fails rows for valid setting names the store has not seeded', function () {
    $this->actingAs($this->superAdminUser());

    // `timezone` is a valid SettingNameEnum value but was never seeded.
    $payload = [
        'format' => 'baxela.module-export',
        'module' => 'setting',
        'version' => 1,
        'sections' => [
            ['entity' => 'settings', 'rows' => [
                ['source_id' => 1, 'payload' => ['name' => 'timezone', 'value' => 'UTC', 'translations' => null]],
            ]],
        ],
    ];

    $response = $this->postJson($this->transferUrl('/admin/imports'), [
        'file' => UploadedFile::fake()->createWithContent('setting.json', json_encode($payload)),
        'on_duplicate' => 'update',
        'dry_run' => false,
    ]);

    $response->assertOk();
    expect($response->json('data.failed_count'))->toBe(1)
        ->and($response->json('data.errors.0'))->toContain('unknown_setting:timezone');
});

it('skips existing settings with the skip strategy', function () {
    $en = $this->defaultLanguage();
    $currency = Currency::factory()->create();
    $this->seedTransferSettings((int) $en->id, (string) $currency->id);
    $this->actingAs($this->superAdminUser());

    $payload = json_decode(
        $this->getJson($this->transferUrl('/admin/exports/setting'))->assertOk()->streamedContent(),
        true
    );

    $response = $this->postJson($this->transferUrl('/admin/imports'), [
        'file' => UploadedFile::fake()->createWithContent('setting.json', json_encode($payload)),
        'on_duplicate' => 'skip',
        'dry_run' => false,
    ]);

    $response->assertOk();
    expect($response->json('data.skipped_count'))->toBe(3)
        ->and(Setting::query()->where('name', 'website_title')->first()->value)->toBe('Old Shop');
});

it('dry run validates without writing', function () {
    $en = $this->defaultLanguage();
    $currency = Currency::factory()->create([CurrencySchema::IS_DEFAULT => true]);
    $this->seedTransferSettings((int) $en->id, (string) $currency->id);
    $this->actingAs($this->superAdminUser());

    $payload = json_decode(
        $this->getJson($this->transferUrl('/admin/exports/setting'))->assertOk()->streamedContent(),
        true
    );
    Setting::query()->delete();

    // A fresh store state: the seeder rows exist, values untouched.
    $this->artisan('db:seed', ['--class' => 'Modules\Setting\Database\Seeders\SettingDatabaseSeeder']);

    $response = $this->postJson($this->transferUrl('/admin/imports'), [
        'file' => UploadedFile::fake()->createWithContent('setting.json', json_encode($payload)),
        'on_duplicate' => 'update',
        'dry_run' => true,
    ]);

    $response->assertOk();
    expect($response->json('data.updated_count'))->toBe(3)
        ->and(Setting::query()->where('name', 'website_title')->value('value'))->not->toBe('Old Shop');
});
