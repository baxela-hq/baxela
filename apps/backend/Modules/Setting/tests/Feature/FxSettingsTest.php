<?php

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Modules\Auth\Models\User;
use Modules\Core\Contracts\Gateways\Setting\FxSettingsResolverInterface;
use Modules\Core\Models\Currency;
use Modules\Core\Models\Language;
use Modules\Core\Schemas\Shared\TenantColumn;
use Modules\Setting\Actions\Admin\Setting\UpdateSettingAction;
use Modules\Setting\Models\Setting;
use Modules\Setting\Schemas\Setting\SettingGroupEnum;
use Modules\Setting\Schemas\Setting\SettingNameEnum;
use Modules\Setting\Schemas\Setting\SettingSchema;
use Modules\Setting\Schemas\Setting\SettingTypeEnum;
use Modules\Setting\Support\PlatformSettings;
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

/** Seed the platform FX rows the way the seeder now does (tenant_id NULL). */
function fxSeedSettings(): void
{
    foreach ([
        [SettingNameEnum::FX_AUTO_SYNC_ENABLED, '0', SettingTypeEnum::BOOLEAN],
        [SettingNameEnum::FX_PROVIDER, 'openexchangerates', SettingTypeEnum::STRING],
        [SettingNameEnum::FX_API_KEY, '', SettingTypeEnum::STRING],
        [SettingNameEnum::FX_SYNC_INTERVAL_HOURS, '24', SettingTypeEnum::INTEGER],
        [SettingNameEnum::FX_SYNC_STATUS, '', SettingTypeEnum::TEXT],
    ] as [$name, $value, $type]) {
        $row = [
            TenantColumn::TENANT_ID => null,
            SettingSchema::NAME => $name->value,
        ];

        DB::table(SettingSchema::TABLE)->updateOrInsert($row, [
            SettingSchema::GROUP => SettingGroupEnum::GENERAL->value,
            SettingSchema::TYPE => $type->value,
            SettingSchema::VALUE => $value,
            SettingSchema::IS_TRANSLATABLE => false,
            SettingSchema::CREATED_AT => now(),
            SettingSchema::UPDATED_AT => now(),
        ]);

        app(PlatformSettings::class)->forget($name->value);
    }

    Cache::forget(ResolvesFxSettings::cacheKey());
}

/** Read one platform FX row without the store scope. */
function fxPlatformValue(string $name): ?string
{
    $value = DB::table(SettingSchema::TABLE)
        ->whereNull(TenantColumn::TENANT_ID)
        ->where(SettingSchema::NAME, $name)
        ->value(SettingSchema::VALUE);

    return is_string($value) ? $value : null;
}

/** Write one platform FX row directly and invalidate both caches. */
function fxSetPlatform(string $name, string $value): void
{
    DB::table(SettingSchema::TABLE)
        ->whereNull(TenantColumn::TENANT_ID)
        ->where(SettingSchema::NAME, $name)
        ->update([SettingSchema::VALUE => $value, SettingSchema::UPDATED_AT => now()]);

    app(PlatformSettings::class)->forget($name);
    Cache::forget(ResolvesFxSettings::cacheKey());
}

it('never exposes the platform api key in store settings responses', function () {
    $this->actingAs($this->superAdminUser());
    fxSeedSettings();

    fxSetPlatform(fxKeyName(), Crypt::encryptString('stored-plaintext-key'));

    $list = collect($this->getJson($this->baseUrl('/admin/settings'))->assertOk()->json('data'));
    expect($list->firstWhere('name', fxKeyName()))->toBeNull();

    $updated = collect($this->patchJson($this->baseUrl('/admin/settings'), [
        ['name' => 'fx_auto_sync_enabled', 'value' => '1', 'translations' => null],
    ])->assertOk()->json('data'));

    expect($updated->firstWhere('name', fxKeyName()))->toBeNull()
        ->and(json_encode($updated->all()))->not->toContain('stored-plaintext-key')
        // The platform write reached the NULL-tenant row, not a store row.
        ->and(fxPlatformValue('fx_auto_sync_enabled'))->toBe('1')
        ->and(Setting::query()->where('name', 'fx_auto_sync_enabled')->exists())->toBeFalse();
});

it('denies platform setting writes to a non-super-admin', function () {
    fxSeedSettings();
    $this->actingAs(User::factory()->active()->create());

    expect(fn () => app(UpdateSettingAction::class)->handle([
        ['name' => 'fx_auto_sync_enabled', 'value' => '1', 'translations' => null],
    ]))->toThrow(AuthorizationException::class)
        ->and(fxPlatformValue('fx_auto_sync_enabled'))->toBe('0');
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
    fxSetPlatform(fxKeyName(), $encrypted);

    $this->patchJson($this->baseUrl('/admin/settings'), [
        ['name' => 'fx_auto_sync_enabled', 'value' => '1', 'translations' => null],
    ])->assertOk();

    expect(fxPlatformValue(fxKeyName()))->toBe($encrypted);

    // resolver still resolves the kept key
    $settings = app(FxSettingsResolverInterface::class)->settings();
    expect($settings['api_key'])->toBe('keep-me')
        ->and($settings['api_key_state'])->toBe(FxSettingsResolverInterface::KEY_STATE_SETTING);
});

it('clears the key on an explicit null submission', function () {
    $this->actingAs($this->superAdminUser());
    fxSeedSettings();

    fxSetPlatform(fxKeyName(), Crypt::encryptString('doomed'));

    $this->patchJson($this->baseUrl('/admin/settings'), [
        ['name' => fxKeyName(), 'value' => null, 'translations' => null],
    ])->assertOk();

    // With no env fallback configured, the row is cleared and the
    // resolver reports "none".
    config(['core.fx.fallback_api_key' => '']);
    Cache::forget(ResolvesFxSettings::cacheKey());

    expect(fxPlatformValue(fxKeyName()))->toBe('');

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

    $stored = fxPlatformValue(fxKeyName());

    expect($stored)->not->toBe('brand-new-secret')
        ->and($stored)->not->toContain('brand-new-secret')
        ->and(Crypt::decryptString((string) $stored))->toBe('brand-new-secret');

    Cache::forget(ResolvesFxSettings::cacheKey());
    $settings = app(FxSettingsResolverInterface::class)->settings();
    expect($settings['api_key'])->toBe('brand-new-secret');
});

it('resolves the env fallback state when nothing is stored', function () {
    fxSeedSettings();
    config(['core.fx.fallback_api_key' => 'env-key']);

    Cache::forget(ResolvesFxSettings::cacheKey());
    $settings = app(FxSettingsResolverInterface::class)->settings();

    expect($settings['api_key'])->toBe('env-key')
        ->and($settings['api_key_state'])->toBe(FxSettingsResolverInterface::KEY_STATE_ENV);
});

it('reports undecryptable instead of silently dropping the key', function () {
    fxSeedSettings();
    config(['core.fx.fallback_api_key' => 'env-key']);

    // A stored value that is not a valid encrypted payload — an
    // APP_KEY-rotation signature (review F21).
    fxSetPlatform(fxKeyName(), 'not-a-cipher');

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
    fxSetPlatform(fxKeyName(), $encrypted);
    fxSetPlatform(fxStatusName(), json_encode(['last_attempt' => ['status' => 'success']]));

    $this->artisan('db:seed', ['--class' => 'Modules\Setting\Database\Seeders\SettingDatabaseSeeder']);

    expect(fxPlatformValue(fxKeyName()))->toBe($encrypted)
        ->and(fxPlatformValue(fxKeyName()))->not->toBe('')
        ->and(fxPlatformValue(fxStatusName()))->toContain('last_attempt');
});
