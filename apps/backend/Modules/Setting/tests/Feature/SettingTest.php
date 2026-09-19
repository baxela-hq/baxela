<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Currency;
use Modules\Core\Models\Language;
use Modules\Core\Schemas\Currency\CurrencySchema;
use Modules\Core\Schemas\Language\LanguageSchema;
use Modules\Setting\Models\Setting;
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

it('persists admin setting updates', function () {
    $this->actingAs($this->superAdminUser());
    Setting::factory()->create(['name' => 'website_title', 'value' => 'Old Title']);

    $this->patchJson($this->baseUrl('/admin/settings'), [
        ['name' => 'website_title', 'value' => 'New Title', 'translations' => null],
    ])->assertOk();

    expect(Setting::query()->where('name', 'website_title')->first()->value)->toBe('New Title');
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
