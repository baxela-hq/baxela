<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Modules\Core\Contracts\Gateways\Setting\FxSettingsResolverInterface;
use Modules\Core\Models\Currency;
use Modules\Core\Models\Language;
use Modules\Setting\Models\Setting;
use Modules\Setting\Schemas\Setting\SettingGroupEnum;
use Modules\Setting\Schemas\Setting\SettingNameEnum;
use Modules\Setting\Schemas\Setting\SettingSchema;
use Modules\Setting\Schemas\Setting\SettingTypeEnum;
use Modules\Setting\Support\ResolvesFxSettings;
use Modules\Setting\Tests\Feature\HelperTrait;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

const FX_KEY_ROW = SettingNameEnum::class.'::FX_API_KEY';

function fxKeyName(): string
{
    return SettingNameEnum::FX_API_KEY->value;
}

function fxStatusName(): string
{
    return SettingNameEnum::FX_SYNC_STATUS->value;
}

/** Seed the FX settings rows the way the seeder would. */
function fxSeedSettings(): void
{
    foreach ([
        [SettingNameEnum::FX_AUTO_SYNC_ENABLED, '0', SettingTypeEnum::BOOLEAN],
        [SettingNameEnum::FX_PROVIDER, 'openexchangerates', SettingTypeEnum::STRING],
        [SettingNameEnum::FX_API_KEY, '', SettingTypeEnum::STRING],
        [SettingNameEnum::FX_SYNC_INTERVAL_HOURS, '24', SettingTypeEnum::INTEGER],
        [SettingNameEnum::FX_SYNC_STATUS, '', SettingTypeEnum::TEXT],
    ] as [$name, $value, $type]) {
        Setting::query()->firstOrCreate(
            [SettingSchema::NAME => $name],
            [
                SettingSchema::GROUP => SettingGroupEnum::GENERAL,
                SettingSchema::TYPE => $type,
                SettingSchema::NAME => $name,
                SettingSchema::IS_TRANSLATABLE => false,
                SettingSchema::VALUE => $value,
            ],
        );
    }

    Cache::forget(ResolvesFxSettings::CACHE_KEY);
}

it('masks the stored api key on the settings list and update response', function () {
    $this->actingAs($this->superAdminUser());
    fxSeedSettings();

    Setting::query()->where('name', fxKeyName())->update([
        'value' => Crypt::encryptString('stored-plaintext-key'),
    ]);
    Cache::forget(ResolvesFxSettings::CACHE_KEY);

    $list = collect($this->getJson($this->baseUrl('/admin/settings'))->assertOk()->json('data'));
    $masked = $list->firstWhere('name', fxKeyName());

    expect($masked['value'])->toBe('********');

    $updated = collect($this->patchJson($this->baseUrl('/admin/settings'), [
        ['name' => 'fx_auto_sync_enabled', 'value' => '1', 'translations' => null],
    ])->assertOk()->json('data'));
    $maskedAfterUpdate = $updated->firstWhere('name', fxKeyName());

    expect($maskedAfterUpdate['value'])->toBe('********');
});

it('rejects the masked placeholder as a submitted key', function () {
    $this->actingAs($this->superAdminUser());
    fxSeedSettings();

    $this->patchJson($this->baseUrl('/admin/settings'), [
        ['name' => fxKeyName(), 'value' => '********', 'translations' => null],
    ])->assertStatus(422);
});

it('keeps the existing key when the key item is omitted from the payload', function () {
    // ConvertEmptyStringsToNull turns '' into null on the wire, so
    // "keep" is expressed by omitting the item (the UI's blank field).
    $this->actingAs($this->superAdminUser());
    fxSeedSettings();

    $encrypted = Crypt::encryptString('keep-me');
    Setting::query()->where('name', fxKeyName())->update(['value' => $encrypted]);
    Cache::forget(ResolvesFxSettings::CACHE_KEY);

    $this->patchJson($this->baseUrl('/admin/settings'), [
        ['name' => 'fx_auto_sync_enabled', 'value' => '1', 'translations' => null],
    ])->assertOk();

    expect(Setting::query()->where('name', fxKeyName())->value('value'))->toBe($encrypted);

    // resolver still resolves the kept key
    $settings = app(FxSettingsResolverInterface::class)->settings();
    expect($settings['api_key'])->toBe('keep-me')
        ->and($settings['api_key_state'])->toBe(FxSettingsResolverInterface::KEY_STATE_SETTING);
});

it('clears the key on an explicit null submission', function () {
    $this->actingAs($this->superAdminUser());
    fxSeedSettings();

    Setting::query()->where('name', fxKeyName())->update([
        'value' => Crypt::encryptString('doomed'),
    ]);
    Cache::forget(ResolvesFxSettings::CACHE_KEY);

    $this->patchJson($this->baseUrl('/admin/settings'), [
        ['name' => fxKeyName(), 'value' => null, 'translations' => null],
    ])->assertOk();

    // With no env fallback configured, the row is cleared and the
    // resolver reports "none".
    config(['core.fx.fallback_api_key' => '']);
    Cache::forget(ResolvesFxSettings::CACHE_KEY);

    expect(Setting::query()->where('name', fxKeyName())->value('value'))->toBe('');

    $settings = app(FxSettingsResolverInterface::class)->settings();
    expect($settings['api_key'])->toBe('')
        ->and($settings['api_key_state'])->toBe(FxSettingsResolverInterface::KEY_STATE_NONE);
});

it('stores the submitted key encrypted at rest', function () {
    $this->actingAs($this->superAdminUser());
    fxSeedSettings();
    config(['core.fx.fallback_api_key' => '']);

    $this->patchJson($this->baseUrl('/admin/settings'), [
        ['name' => fxKeyName(), 'value' => 'brand-new-secret', 'translations' => null],
    ])->assertOk();

    $stored = Setting::query()->where('name', fxKeyName())->value('value');

    expect($stored)->not->toBe('brand-new-secret')
        ->and($stored)->not->toContain('brand-new-secret')
        ->and(Crypt::decryptString($stored))->toBe('brand-new-secret');

    Cache::forget(ResolvesFxSettings::CACHE_KEY);
    $settings = app(FxSettingsResolverInterface::class)->settings();
    expect($settings['api_key'])->toBe('brand-new-secret');
});

it('resolves the env fallback state when nothing is stored', function () {
    fxSeedSettings();
    config(['core.fx.fallback_api_key' => 'env-key']);

    Cache::forget(ResolvesFxSettings::CACHE_KEY);
    $settings = app(FxSettingsResolverInterface::class)->settings();

    expect($settings['api_key'])->toBe('env-key')
        ->and($settings['api_key_state'])->toBe(FxSettingsResolverInterface::KEY_STATE_ENV);
});

it('reports undecryptable instead of silently dropping the key', function () {
    fxSeedSettings();
    config(['core.fx.fallback_api_key' => 'env-key']);

    // A stored value that is not a valid encrypted payload — an
    // APP_KEY-rotation signature (review F21).
    Setting::query()->where('name', fxKeyName())->update(['value' => 'not-a-cipher']);
    Cache::forget(ResolvesFxSettings::CACHE_KEY);

    $settings = app(FxSettingsResolverInterface::class)->settings();

    expect($settings['api_key'])->toBe('')
        ->and($settings['api_key_state'])->toBe(FxSettingsResolverInterface::KEY_STATE_UNDECRYPTABLE);
});

it('rejects writes to the machine-managed sync status', function () {
    $this->actingAs($this->superAdminUser());
    fxSeedSettings();

    $this->patchJson($this->baseUrl('/admin/settings'), [
        ['name' => fxStatusName(), 'value' => '{"hacked":true}', 'translations' => null],
    ])->assertStatus(422);
});

it('preserves configured credentials when the seeder re-runs', function () {
    fxSeedSettings();
    // The seeder reads the default language/currency through the Core
    // gateway — seed the reference data first.
    Language::factory()->create(['is_default' => true]);
    Currency::factory()->create(['is_default' => true]);

    $encrypted = Crypt::encryptString('survives-reseed');
    Setting::query()->where('name', fxKeyName())->update(['value' => $encrypted]);
    Setting::query()->where('name', fxStatusName())->update([
        'value' => json_encode(['last_attempt' => ['status' => 'success']]),
    ]);

    $this->artisan('db:seed', ['--class' => 'Modules\Setting\Database\Seeders\SettingDatabaseSeeder']);

    expect(Setting::query()->where('name', fxKeyName())->value('value'))->toBe($encrypted)
        ->and(Setting::query()->where('name', fxKeyName())->value('value'))->not->toBe('')
        ->and(Setting::query()->where('name', fxStatusName())->value('value'))->toContain('last_attempt');
});
