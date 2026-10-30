<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Modules\Auth\Models\User;
use Modules\Core\Models\Currency;
use Modules\Core\Models\Language;
use Modules\Core\Schemas\Currency\CurrencySchema;
use Modules\Core\Schemas\Language\LanguageSchema;
use Modules\Setting\Models\Setting;
use Modules\Setting\Support\AppliesStoreTimezone;
use Modules\Setting\Tests\Feature\HelperTrait;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

it('exposes only the public settings publicly', function () {
    Setting::factory()->create(['name' => 'website_title', 'value' => 'Baxela Shop']);
    Setting::factory()->create(['name' => 'language_id', 'value' => '1']);

    $response = $this->getJson($this->baseUrl('/public/settings'))
        ->assertOk();

    $names = collect($response->json('data'))->pluck('name');

    expect($names)->toContain('website_title')
        ->not->toContain('language_id');
});

it('exposes the storefront theme setting publicly', function () {
    Setting::factory()->create(['name' => 'storefront_theme', 'value' => 'default']);

    $response = $this->getJson($this->baseUrl('/public/settings'))
        ->assertOk();

    $theme = collect($response->json('data'))
        ->firstWhere('name', 'storefront_theme');

    expect($theme)->not->toBeNull()
        ->and($theme['value'])->toBe('default');
});

it('persists admin setting updates', function () {
    $this->actingAs($this->superAdminUser());
    Setting::factory()->create(['name' => 'website_title', 'value' => 'Old Title']);

    $this->patchJson($this->baseUrl('/admin/settings'), [
        ['name' => 'website_title', 'value' => 'New Title', 'translations' => null],
    ])->assertOk();

    expect(Setting::query()->where('name', 'website_title')->first()->value)->toBe('New Title');
});

it('persists the storefront theme setting', function () {
    $this->actingAs($this->superAdminUser());
    Setting::factory()->create(['name' => 'storefront_theme', 'value' => 'default']);

    $this->patchJson($this->baseUrl('/admin/settings'), [
        ['name' => 'storefront_theme', 'value' => 'midnight', 'translations' => null],
    ])->assertOk();

    expect(Setting::query()->where('name', 'storefront_theme')->first()->value)->toBe('midnight');
});

it('syncs the default language and currency flags onto the core records', function () {
    $this->actingAs($this->superAdminUser());
    Setting::factory()->create(['name' => 'language_id', 'value' => '1']);
    Setting::factory()->create(['name' => 'currency_id', 'value' => '1']);
    $oldLanguage = Language::factory()->create([LanguageSchema::IS_DEFAULT => true]);
    $newLanguage = Language::factory()->create([LanguageSchema::IS_DEFAULT => false]);
    $oldCurrency = Currency::factory()->create([CurrencySchema::IS_DEFAULT => true]);
    $newCurrency = Currency::factory()->create([CurrencySchema::IS_DEFAULT => false]);

    $this->patchJson($this->baseUrl('/admin/settings'), [
        ['name' => 'language_id', 'value' => (string) $newLanguage->id, 'translations' => null],
        ['name' => 'currency_id', 'value' => (string) $newCurrency->id, 'translations' => null],
    ])->assertOk();

    expect($newLanguage->refresh()->{LanguageSchema::IS_DEFAULT})->toBeTrue()
        ->and($oldLanguage->refresh()->{LanguageSchema::IS_DEFAULT})->toBeFalse()
        ->and($newCurrency->refresh()->{CurrencySchema::IS_DEFAULT})->toBeTrue()
        ->and($oldCurrency->refresh()->{CurrencySchema::IS_DEFAULT})->toBeFalse();
});

it('denies setting writes without permissions', function () {
    $this->actingAs(User::factory()->create(['is_active' => true]));

    $this->patchJson($this->baseUrl('/admin/settings'), [
        ['name' => 'title', 'value' => 'Nope'],
    ])->assertStatus(403);
});

it('stores and rejects timezone settings with identifier validation', function () {
    $this->actingAs($this->superAdminUser());
    Setting::factory()->create(['name' => 'timezone', 'value' => 'UTC']);

    $this->patchJson($this->baseUrl('/admin/settings'), [
        ['name' => 'timezone', 'value' => 'Asia/Tehran'],
    ])->assertOk();

    expect(Setting::query()
        ->where('name', 'timezone')
        ->value('value'))->toBe('Asia/Tehran');

    $this->patchJson($this->baseUrl('/admin/settings'), [
        ['name' => 'timezone', 'value' => 'Mars/Olympus_Mons'],
    ])->assertStatus(422);
});

it('applies the store timezone over the app default at boot', function () {
    Setting::query()->create([
        'name' => 'timezone',
        'value' => 'Asia/Tehran',
        'group' => 'general',
        'type' => 'string',
        'is_translatable' => false,
    ]);
    Cache::forget(AppliesStoreTimezone::CACHE_KEY);

    config(['app.timezone' => 'UTC']);

    (new AppliesStoreTimezone)();

    expect(config('app.timezone'))->toBe('Asia/Tehran')
        ->and(date_default_timezone_get())->toBe('Asia/Tehran');

    // garbage in the DB row falls back to the configured default
    Setting::query()->where('name', 'timezone')->update(['value' => 'not-a-zone']);
    Cache::forget(AppliesStoreTimezone::CACHE_KEY);
    config(['app.timezone' => 'UTC']);
    date_default_timezone_set('UTC');

    (new AppliesStoreTimezone)();

    expect(config('app.timezone'))->toBe('UTC');
});

it('serves the timezone setting on the public whitelist', function () {
    Setting::query()->create([
        'name' => 'timezone',
        'value' => 'UTC',
        'group' => 'general',
        'type' => 'string',
        'is_translatable' => false,
    ]);

    $names = $this->getJson('api/v1/setting/public/settings')
        ->assertOk()
        ->collect('data')->pluck('name');

    expect($names)->toContain('timezone');
});
