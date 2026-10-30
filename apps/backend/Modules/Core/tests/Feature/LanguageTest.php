<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\Models\User;
use Modules\Core\Models\Language;
use Modules\Core\Schemas\Language\LanguageSchema;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->superAdmin = User::factory()->superAdmin()->create();
});

function languageRow(array $attributes = []): Language
{
    return Language::query()->create(array_merge([
        LanguageSchema::LOCALE => 'tr-TR',
        LanguageSchema::NAME => 'Turkish',
        LanguageSchema::NATIVE_NAME => 'Türkçe',
        LanguageSchema::CODE => 'tr',
        LanguageSchema::CODE3 => 'tur',
        LanguageSchema::IS_RTL => false,
        LanguageSchema::IS_ACTIVE => true,
        LanguageSchema::IS_DEFAULT => false,
        LanguageSchema::POSITION => 10,
    ], $attributes));
}

it('lists active languages by default and all languages with ?all=1', function () {
    $this->actingAs($this->superAdmin);

    languageRow(['code' => 'tr']);
    languageRow(['code' => 'el', 'locale' => 'el-GR', 'is_active' => false, 'position' => 11]);

    $active = $this->getJson('api/v1/core/admin/languages')->assertOk();
    expect(count($active->json('data')))->toBe(1);

    $all = $this->getJson('api/v1/core/admin/languages?all=1')->assertOk();
    expect(count($all->json('data')))->toBe(2);
});

it('deactivates and reactivates a language', function () {
    $this->actingAs($this->superAdmin);
    Language::query()->create([
        LanguageSchema::LOCALE => 'en-US', LanguageSchema::NAME => 'English',
        LanguageSchema::NATIVE_NAME => 'English', LanguageSchema::CODE => 'en',
        LanguageSchema::CODE3 => 'eng', LanguageSchema::IS_RTL => false,
        LanguageSchema::IS_ACTIVE => true, LanguageSchema::IS_DEFAULT => true,
        LanguageSchema::POSITION => 1,
    ]);
    $german = languageRow(['code' => 'de', 'locale' => 'de-DE', 'name' => 'German']);

    $this->patchJson("api/v1/core/admin/languages/{$german->id}", ['is_active' => false])
        ->assertOk()
        ->assertJsonPath('data.is_active', false);

    $this->patchJson("api/v1/core/admin/languages/{$german->id}", ['is_active' => true])
        ->assertOk()
        ->assertJsonPath('data.is_active', true);
});

it('refuses to deactivate the default language', function () {
    $this->actingAs($this->superAdmin);

    $english = languageRow(['code' => 'en', 'locale' => 'en-US', 'is_default' => true]);
    languageRow(['code' => 'tr']);

    $this->patchJson("api/v1/core/admin/languages/{$english->id}", ['is_active' => false])
        ->assertStatus(400)
        ->assertJsonPath('code', 'core.language.cannot_deactivate_default');
});

it('refuses to deactivate the last active language', function () {
    $this->actingAs($this->superAdmin);

    // synthetic state (a default would trip the default-guard first), but it
    // exercises the second guard: one active row left, not the default
    languageRow(['code' => 'en', 'locale' => 'en-US', 'is_default' => true, 'is_active' => false]);
    $only = languageRow(['code' => 'tr']);

    $this->patchJson("api/v1/core/admin/languages/{$only->id}", ['is_active' => false])
        ->assertStatus(400)
        ->assertJsonPath('code', 'core.language.last_active');
});

it('refuses to activate a language with no shipped locale files', function () {
    $this->actingAs($this->superAdmin);

    $afrikaans = languageRow([
        'code' => 'af', 'locale' => 'af-ZA', 'name' => 'Afrikaans', 'is_active' => false,
    ]);

    $this->patchJson("api/v1/core/admin/languages/{$afrikaans->id}", ['is_active' => true])
        ->assertStatus(400)
        ->assertJsonPath('code', 'core.language.not_implemented');

    expect($afrikaans->fresh()->is_active)->toBeFalse();
});

it('exposes is_implemented on the admin list and still allows deactivating unimplemented rows', function () {
    $this->actingAs($this->superAdmin);

    // companion so deactivating Zulu doesn't trip the last-active guard
    languageRow(['code' => 'en', 'locale' => 'en-US', 'is_active' => true]);

    $zulu = languageRow([
        'code' => 'zu', 'locale' => 'zu-ZA', 'name' => 'Zulu', 'is_active' => true,
    ]);

    $row = $this->getJson('api/v1/core/admin/languages?all=1')
        ->assertOk()
        ->collect('data')->firstWhere('code', 'zu');

    expect($row['is_implemented'])->toBeFalse();

    $this->patchJson("api/v1/core/admin/languages/{$zulu->id}", ['is_active' => false])
        ->assertOk()
        ->assertJsonPath('data.is_active', false);
});

it('serves active languages publicly for the storefront switcher', function () {
    languageRow(['code' => 'tr']);
    languageRow(['code' => 'el', 'locale' => 'el-GR', 'is_active' => false, 'position' => 11]);

    $this->getJson('api/v1/core/public/languages')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.code', 'tr')
        ->assertJsonPath('data.0.native_name', 'Türkçe');
});

it('denies language management without permissions', function () {
    $this->actingAs(User::factory()->create(['is_active' => true]));

    $turkish = languageRow();

    $this->patchJson("api/v1/core/admin/languages/{$turkish->id}", ['is_active' => false])
        ->assertStatus(403);
    $this->getJson('api/v1/core/admin/languages?all=1')->assertStatus(403);
});
