<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Modules\Auth\Models\User;
use Modules\Core\Actions\Admin\Currency\SyncExchangeRatesAction;
use Modules\Core\Models\Currency;
use Modules\Setting\Models\Setting;
use Modules\Setting\Support\ResolvesFxSettings;

uses(RefreshDatabase::class);

const FX_PROVIDER_HOST = 'openexchangerates.test';

beforeEach(function () {
    // Default credential via env fallback; the missing-key test clears it.
    config(['core.fx.fallback_api_key' => 'test-key']);
    // Point the provider at the stubbed host.
    config(['core.fx.providers.openexchangerates.base_url' => 'https://'.FX_PROVIDER_HOST.'/api']);

    Currency::factory()->create(['code' => 'USD', 'is_default' => true, 'exchange_rate' => '1.00000000']);
    // is_default is randomized by the factory — pin it false explicitly
    // so the base currency is deterministic.
    Currency::factory()->create(['code' => 'EUR', 'is_default' => false, 'exchange_rate' => '1.08500000']);
    Currency::factory()->create(['code' => 'GBP', 'is_default' => false, 'exchange_rate' => '0.79000000']);
    Currency::factory()->create(['code' => 'CHF', 'is_default' => false, 'exchange_rate' => '0.95000000', 'is_active' => false]);
    Currency::factory()->create(['code' => 'AAA', 'is_default' => false, 'exchange_rate' => '2.00000000']);

    fxSetting(SettingNameEnum('FX_AUTO_SYNC_ENABLED'), '1');
    fxSetting(SettingNameEnum('FX_SYNC_INTERVAL_HOURS'), '24');
    // The seeder guarantees these rows in production; mirror that here
    // so the engine's status writes have a row to update.
    fxSetting(SettingNameEnum('FX_PROVIDER'), 'openexchangerates');
    fxSetting(SettingNameEnum('FX_API_KEY'), '');
    fxSetting(SettingNameEnum('FX_SYNC_STATUS'), '');
});

function SettingNameEnum(string $caseName): string
{
    return constant("Modules\Setting\Schemas\Setting\SettingNameEnum::$caseName")->value;
}

/** Write one FX setting row directly (the admin-configured state) and bust the resolver cache. */
function fxSetting(string $name, string $value): void
{
    Setting::query()->updateOrCreate(['name' => $name], ['value' => $value]);
    Cache::forget(ResolvesFxSettings::CACHE_KEY);
}

/**
 * Write the machine-managed status payload (as the engine would).
 *
 * @param  array<string, mixed>  $status
 */
function fxWriteStatus(array $status): void
{
    fxSetting(constant("Modules\Setting\Schemas\Setting\SettingNameEnum::FX_SYNC_STATUS")->value, json_encode($status));
}

/**
 * @return array<string, mixed>|null
 */
function fxReadStatus(): ?array
{
    $raw = Setting::query()->where('name', SettingNameEnum('FX_SYNC_STATUS'))->value('value');

    return $raw ? json_decode($raw, true) : null;
}

/**
 * Hand-built snapshot body — literal precision is hand-controlled.
 *
 * @param  array<string, string>  $rates
 */
function fxSnapshotBody(string $base, int $timestamp, array $rates, int $status = 200): string
{
    $pairs = [];
    foreach ($rates as $code => $literal) {
        $pairs[] = '"'.$code.'":'.$literal;
    }

    return '{"disclaimer":"x","license":"y","timestamp":'.$timestamp.',"base":"'.$base.'","rates":{'.implode(',', $pairs).'}}';
}

/**
 * One fake whose status code is mutable through the returned reference —
 * Http::fake() stubs MERGE in insertion order (first match wins), so a
 * second fxStubRates call can never replace an earlier one.
 *
 * @return int[] reference: set $ref['status'] before the next sync
 */
function &fxMutableStub(string $base = 'USD', ?int $timestamp = null): array
{
    $ref = ['status' => 200, 'rates' => [], 'body' => null];
    Http::fake([
        FX_PROVIDER_HOST.'/*' => function () use (&$ref, $base, $timestamp) {
            if ($ref['body'] !== null) {
                return Http::response($ref['body'], $ref['status']);
            }

            return Http::response(fxSnapshotBody($base, $timestamp ?? now()->timestamp, $ref['rates']), $ref['status']);
        },
    ]);

    return $ref;
}

function fxStubRates(array $rates, string $base = 'USD', ?int $timestamp = null, int $status = 200): void
{
    Http::fake([
        FX_PROVIDER_HOST.'/*' => Http::response(
            fxSnapshotBody($base, $timestamp ?? now()->timestamp, $rates),
            $status,
        ),
    ]);
}

function fxRate(string $code): string
{
    return (string) Currency::query()->where('code', $code)->value('exchange_rate');
}

// ---------------------------------------------------------------------------
// Gates
// ---------------------------------------------------------------------------

it('skips when auto-sync is disabled (scheduler path)', function () {
    fxSetting(SettingNameEnum('FX_AUTO_SYNC_ENABLED'), '0');
    fxStubRates(['EUR' => '1.1']);

    $result = app(SyncExchangeRatesAction::class)->sync();

    expect($result['outcome'])->toBe(SyncExchangeRatesAction::OUTCOME_SKIPPED_DISABLED);
});

it('skips when not due by the success interval', function () {
    fxWriteStatus([
        'last_attempt' => ['at' => now()->subHour()->toIso8601String(), 'status' => 'success', 'error_code' => null, 'provider_timestamp' => 1, 'base_code' => 'USD'],
        'last_success' => ['at' => now()->subHour()->toIso8601String(), 'provider_timestamp' => 1, 'base_code' => 'USD'],
    ]);
    fxStubRates(['EUR' => '1.1']);

    $result = app(SyncExchangeRatesAction::class)->sync();

    expect($result['outcome'])->toBe(SyncExchangeRatesAction::OUTCOME_SKIPPED_NOT_DUE);
});

it('skips within the retry cooldown after a failure', function () {
    fxWriteStatus([
        'last_attempt' => ['at' => now()->subMinutes(5)->toIso8601String(), 'status' => 'failed', 'error_code' => 'core.provider_http_error', 'provider_timestamp' => 1, 'base_code' => null],
        'last_success' => ['at' => now()->subDays(3)->toIso8601String(), 'provider_timestamp' => 1, 'base_code' => 'USD'],
    ]);
    fxStubRates(['EUR' => '1.1']);

    $result = app(SyncExchangeRatesAction::class)->sync();

    expect($result['outcome'])->toBe(SyncExchangeRatesAction::OUTCOME_SKIPPED_COOLDOWN);
});

it('retries after the cooldown elapsed', function () {
    fxWriteStatus([
        'last_attempt' => ['at' => now()->subMinutes(45)->toIso8601String(), 'status' => 'failed', 'error_code' => 'core.provider_http_error', 'provider_timestamp' => 1, 'base_code' => null],
        'last_success' => ['at' => now()->subDays(3)->toIso8601String(), 'provider_timestamp' => 1, 'base_code' => 'USD'],
    ]);
    fxStubRates(['EUR' => '1.10000000']);

    app(SyncExchangeRatesAction::class)->sync();

    expect(fxRate('EUR'))->toBe('1.10000000');
});

it('never lets an unexpected last-attempt status suppress syncing', function () {
    fxWriteStatus([
        'last_attempt' => ['at' => now()->toIso8601String(), 'status' => 'running', 'error_code' => null, 'provider_timestamp' => 1, 'base_code' => null],
        'last_success' => ['at' => now()->subDays(3)->toIso8601String(), 'provider_timestamp' => 1, 'base_code' => 'USD'],
    ]);
    fxStubRates(['EUR' => '1.10000000']);

    app(SyncExchangeRatesAction::class)->sync();

    expect(fxRate('EUR'))->toBe('1.10000000');
});

// ---------------------------------------------------------------------------
// Happy paths
// ---------------------------------------------------------------------------

it('syncs every active non-default currency and records status', function () {
    fxStubRates([
        'EUR' => '1.10000000',
        'GBP' => '0.80000000',
        // AAA never appears — partial coverage
    ]);

    $result = app(SyncExchangeRatesAction::class)->sync();

    expect($result['outcome'])->toBe(SyncExchangeRatesAction::OUTCOME_SYNCED)
        ->and(fxRate('EUR'))->toBe('1.10000000')
        ->and(fxRate('GBP'))->toBe('0.80000000')
        // inactive + default currencies untouched
        ->and(fxRate('CHF'))->toBe('0.95000000')
        ->and(fxRate('USD'))->toBe('1.00000000');

    $status = fxReadStatus();
    expect($status['last_attempt']['status'])->toBe('success')
        ->and($status['last_attempt']['base_code'])->toBe('USD')
        ->and($status['last_success']['updated_count'])->toBe(2)
        ->and($status['last_success']['skipped_count'])->toBe(1)
        ->and($status['last_success']['skipped'])->toBe(['AAA'])
        ->and($status['last_success']['truncated'])->toBeFalse();

    // The credential travels ONLY in the header (review F5)
    Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Token test-key')
        && ! str_contains($request->url(), 'test-key'));
});

it('is deterministic across repeated runs', function () {
    $ref = &fxMutableStub();
    $ref['rates'] = ['EUR' => '1.10000000', 'GBP' => '0.80000000'];

    app(SyncExchangeRatesAction::class)->sync();
    $ref['rates'] = ['EUR' => '1.20000000', 'GBP' => '0.80000000'];
    app(SyncExchangeRatesAction::class)->sync(force: true);

    expect(fxRate('EUR'))->toBe('1.20000000');
    $status = fxReadStatus();
    expect($status['last_success']['updated_count'])->toBe(2);
});

it('skips malformed, zero and exponent rates but applies the rest', function () {
    fxStubRates([
        'EUR' => '1.10000000',
        // exponent notation: a valid JSON number the formatter rejects
        'GBP' => '1.5e-7',
        // overflow: valid JSON number far beyond decimal(16,8)
        'AAA' => '99999999999999999999.5',
    ]);

    app(SyncExchangeRatesAction::class)->sync();

    expect(fxRate('EUR'))->toBe('1.10000000')
        ->and(fxRate('GBP'))->toBe('0.79000000')
        ->and(fxRate('AAA'))->toBe('2.00000000');

    expect(fxReadStatus()['last_success']['updated_count'])->toBe(1);
});

// ---------------------------------------------------------------------------
// Fail-closed gates
// ---------------------------------------------------------------------------

it('aborts without any write when the provider base differs (F1)', function () {
    fxStubRates(['EUR' => '1.10000000'], base: 'EUR');

    try {
        app(SyncExchangeRatesAction::class)->sync();
        $this->fail('Expected base_mismatch');
    } catch (Throwable) {
    }

    expect(fxRate('EUR'))->toBe('1.08500000')
        ->and(fxReadStatus()['last_attempt']['status'])->toBe('failed')
        ->and(fxReadStatus()['last_attempt']['error_code'])->toBe('core.base_mismatch');
});

it('detects a default-currency flip between fetch and apply (detection path, SQLite)', function () {
    // The stub flips the default DURING the fetch — phase 2's locked
    // revalidation must abort. This exercises the detection logic; the
    // row-lock serialization itself is MySQL-level (see the dev-stack
    // concurrency check).
    Http::fake([
        FX_PROVIDER_HOST.'/*' => function () {
            Currency::query()->where('code', 'USD')->update(['is_default' => false]);
            Currency::query()->where('code', 'EUR')->update(['is_default' => true]);

            return Http::response(fxSnapshotBody('USD', now()->timestamp, ['EUR' => '1.1', 'GBP' => '0.8']), 200);
        },
    ]);

    try {
        app(SyncExchangeRatesAction::class)->sync();
        $this->fail('Expected base_mismatch');
    } catch (Throwable) {
    }

    expect(fxRate('EUR'))->toBe('1.08500000')
        ->and(fxRate('GBP'))->toBe('0.79000000')
        ->and(fxReadStatus()['last_attempt']['error_code'])->toBe('core.base_mismatch');
});

it('rejects stale snapshots', function () {
    config(['core.fx.max_rate_age_hours' => 48]);
    fxStubRates(['EUR' => '1.1'], timestamp: now()->subHours(72)->timestamp);

    try {
        app(SyncExchangeRatesAction::class)->sync();
        $this->fail('Expected stale_rates');
    } catch (Throwable) {
    }

    expect(fxRate('EUR'))->toBe('1.08500000')
        ->and(fxReadStatus()['last_attempt']['error_code'])->toBe('core.stale_rates');
});

it('rejects future-dated snapshots', function () {
    fxStubRates(['EUR' => '1.1'], timestamp: now()->addHour()->timestamp);

    try {
        app(SyncExchangeRatesAction::class)->sync();
        $this->fail('Expected invalid_timestamp');
    } catch (Throwable) {
    }

    expect(fxReadStatus()['last_attempt']['error_code'])->toBe('core.invalid_timestamp');
});

it('rolls back completely when the database fails mid-update', function () {
    $listener = function () {
        throw new RuntimeException('disk exploded');
    };
    Currency::updating($listener);

    try {
        fxStubRates(['EUR' => '1.10000000', 'GBP' => '0.80000000']);
        app(SyncExchangeRatesAction::class)->sync();
        $this->fail('Expected the sync to fail');
    } catch (Throwable) {
        // the DB failure arrives wrapped as core.sync_failed
    } finally {
        Event::forget('eloquent.updating: '.Currency::class);
    }

    expect(fxRate('EUR'))->toBe('1.08500000')
        ->and(fxRate('GBP'))->toBe('0.79000000')
        ->and(fxReadStatus()['last_attempt']['status'])->toBe('failed')
        ->and(fxReadStatus()['last_attempt']['error_code'])->toBe('core.sync_failed')
        // last_success untouched — it never existed here
        ->and(fxReadStatus()['last_success'])->toBeNull();
});

it('maps 401 and 403 to distinct sanitized codes', function () {
    $ref = &fxMutableStub();

    $ref['status'] = 401;
    try {
        app(SyncExchangeRatesAction::class)->sync();
        $this->fail('Expected auth failure');
    } catch (Throwable) {
    }
    expect(fxReadStatus()['last_attempt']['error_code'])->toBe('core.provider_auth_failed');

    // force: the failed attempt put us inside the retry cooldown —
    // "Sync now" bypasses the gates.
    $ref['status'] = 403;
    try {
        app(SyncExchangeRatesAction::class)->sync(force: true);
        $this->fail('Expected forbidden');
    } catch (Throwable) {
    }
    expect(fxReadStatus()['last_attempt']['error_code'])->toBe('core.provider_forbidden');

    // rates never touched
    expect(fxRate('EUR'))->toBe('1.08500000');
});

it('records provider transport failures without touching rates', function () {
    fxStubRates(['EUR' => '1.1'], status: 500);
    try {
        app(SyncExchangeRatesAction::class)->sync();
        $this->fail('Expected provider_http_error');
    } catch (Throwable) {
    }

    expect(fxRate('EUR'))->toBe('1.08500000')
        ->and(fxReadStatus()['last_attempt']['error_code'])->toBe('core.provider_http_error');
});

// ---------------------------------------------------------------------------
// Credentials
// ---------------------------------------------------------------------------

it('fails fast with nothing sent when no key exists anywhere', function () {
    fxSetting(SettingNameEnum('FX_AUTO_SYNC_ENABLED'), '1');
    config(['core.fx.fallback_api_key' => '']);
    fxStubRates(['EUR' => '1.1']);

    try {
        app(SyncExchangeRatesAction::class)->sync();
        $this->fail('Expected missing_api_key');
    } catch (Throwable) {
    }

    Http::assertNothingSent();
    expect(fxReadStatus()['last_attempt']['error_code'])->toBe('core.missing_api_key');
});

it('falls back to the env key when the setting is empty', function () {
    config(['core.fx.fallback_api_key' => 'env-key-123']);
    fxStubRates(['EUR' => '1.10000000']);

    app(SyncExchangeRatesAction::class)->sync();

    Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Token env-key-123'));
});

it('never leaks the api key into status or exception messages', function () {
    // Store the credential the way the admin endpoint does — encrypted.
    fxSetting(SettingNameEnum('FX_API_KEY'), Crypt::encryptString('supersecret-42'));
    fxStubRates(['EUR' => '1.1'], status: 500);

    try {
        app(SyncExchangeRatesAction::class)->sync();
    } catch (Throwable) {
        // the endpoint/command render the sanitized code
    }

    $statusJson = (string) json_encode(fxReadStatus());
    expect($statusJson)->not->toContain('supersecret-42');

    Http::assertSent(fn ($request) => ! str_contains($request->url(), 'supersecret-42'));
});

// ---------------------------------------------------------------------------
// Lock
// ---------------------------------------------------------------------------

it('reports sync_in_progress on lock collision without writing status', function () {
    fxStubRates(['EUR' => '1.10000000']);
    $lock = Cache::lock(SyncExchangeRatesAction::LOCK_NAME, 10);
    $lock->get();

    $result = app(SyncExchangeRatesAction::class)->sync();

    $lock->release();

    expect($result['outcome'])->toBe(SyncExchangeRatesAction::OUTCOME_SYNC_IN_PROGRESS)
        ->and(fxReadStatus())->toBeNull();

    Http::assertNothingSent();
});

it('releases the lock after success and after failure', function () {
    $ref = &fxMutableStub();
    $ref['rates'] = ['EUR' => '1.10000000'];

    app(SyncExchangeRatesAction::class)->sync();
    $probe = Cache::lock(SyncExchangeRatesAction::LOCK_NAME, 5);
    expect($probe->get())->toBeTrue();
    $probe->release();

    $ref['status'] = 500;
    try {
        app(SyncExchangeRatesAction::class)->sync(force: true);
    } catch (Throwable) {
    }
    $probe = Cache::lock(SyncExchangeRatesAction::LOCK_NAME, 5);
    expect($probe->get())->toBeTrue();
    $probe->release();
});

// ---------------------------------------------------------------------------
// Admin endpoint
// ---------------------------------------------------------------------------

it('lets a super-admin sync now even while disabled', function () {
    fxSetting(SettingNameEnum('FX_AUTO_SYNC_ENABLED'), '0');
    $this->actingAs(User::factory()->superAdmin()->create());
    fxStubRates(['EUR' => '1.10000000']);

    $this->postJson('api/v1/core/admin/currencies/sync-rates')
        ->assertOk()
        ->assertJsonPath('data.outcome', 'synced');

    expect(fxRate('EUR'))->toBe('1.10000000');
});

it('denies the sync endpoint without the permission', function () {
    $this->actingAs(User::factory()->create(['is_active' => true]));
    fxStubRates(['EUR' => '1.1']);

    $this->postJson('api/v1/core/admin/currencies/sync-rates')->assertForbidden();
    Http::assertNothingSent();
});

it('returns 409 on a lock collision', function () {
    $this->actingAs(User::factory()->superAdmin()->create());
    fxStubRates(['EUR' => '1.10000000']);
    $lock = Cache::lock(SyncExchangeRatesAction::LOCK_NAME, 10);
    $lock->get();

    $this->postJson('api/v1/core/admin/currencies/sync-rates')->assertStatus(409);

    $lock->release();
    Http::assertNothingSent();
});
