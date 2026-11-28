<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Modules\Core\Contracts\Gateways\Setting\FxSettingsResolverInterface;
use Modules\Core\Contracts\Gateways\Setting\PlatformSettingsInterface;
use Modules\Core\Contracts\Gateways\Tenant\StoreRuntimeInterface;
use Modules\Core\Exceptions\PlatformSettingMissing;
use Modules\Core\Models\Language;
use Modules\Core\Schemas\Shared\TenantColumn;
use Modules\Core\Support\PicksTranslation;
use Modules\Core\Support\SupportedLocales;
use Modules\Core\Support\Tenancy\CentralOperationRegistry;
use Modules\Core\Support\Tenancy\TenantContext;
use Modules\Setting\Schemas\Setting\SettingNameEnum;
use Modules\Setting\Schemas\Setting\SettingSchema;
use Modules\Setting\Support\AppliesStoreIdentity;
use Modules\Setting\Support\AppliesStoreTimezone;
use Modules\Setting\Support\PlatformSettings;
use Modules\Setting\Support\ResolvesFxSettings;
use Modules\Setting\Support\ResolvesPricingMode;
use Modules\Setting\Tests\Fixtures\RecordsStoreRuntimeJob;
use Modules\Tenant\Tests\Feature\HelperTrait;
use Modules\Tenant\Tests\Fixtures\ProbeCentralCaller;
use Modules\Tenant\Tests\Fixtures\ProbeOperation;
use Modules\Tenant\Tests\Fixtures\RecordsContextJob;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

beforeEach(function () {
    config(['cache.default' => 'array']);
    Cache::flush();
    RecordsStoreRuntimeJob::flush();
    RecordsContextJob::flush();
    ProbeCentralCaller::flush();
});

/** Insert one NULL-tenant platform row directly and bust its caches. */
function storeRuntimePlatform(string $name, string $value): void
{
    DB::table(SettingSchema::TABLE)->updateOrInsert(
        [TenantColumn::TENANT_ID => null, SettingSchema::NAME => $name],
        [
            SettingSchema::VALUE => $value,
            SettingSchema::CREATED_AT => now(),
            SettingSchema::UPDATED_AT => now(),
        ],
    );

    app(PlatformSettings::class)->forget($name);
    Cache::forget(ResolvesFxSettings::cacheKey());
}

/** The platform rows the runtime needs before a store request runs. */
function storeRuntimeSeedPlatform(): void
{
    storeRuntimePlatform(PlatformSettingsInterface::FX_AUTO_SYNC_ENABLED, '0');
    storeRuntimePlatform(PlatformSettingsInterface::FX_PROVIDER, 'openexchangerates');
    storeRuntimePlatform(PlatformSettingsInterface::FX_API_KEY, '');
    storeRuntimePlatform(PlatformSettingsInterface::FX_SYNC_INTERVAL_HOURS, '24');
    storeRuntimePlatform(PlatformSettingsInterface::FX_SYNC_STATUS, '');
    storeRuntimePlatform(
        PlatformSettingsInterface::PLATFORM_IDENTITY,
        json_encode(['name' => 'Baxela', 'url' => 'https://baxela.test'], JSON_THROW_ON_ERROR),
    );
}

/** Insert a store-owned setting without depending on the ambient context. */
function storeRuntimeSetting(int $tenantId, string $name, string $value): void
{
    DB::table(SettingSchema::TABLE)->updateOrInsert(
        [TenantColumn::TENANT_ID => $tenantId, SettingSchema::NAME => $name],
        [
            SettingSchema::VALUE => $value,
            SettingSchema::CREATED_AT => now(),
            SettingSchema::UPDATED_AT => now(),
        ],
    );
}

it('never reads a store-dependent cache value written under another store', function () {
    storeRuntimeSeedPlatform();
    Language::factory()->create(['code' => 'en', 'is_active' => true, 'is_default' => true]);

    $a = (int) $this->createTenant('acme')->getKey();
    $b = (int) $this->createTenant('other')->getKey();

    foreach ([[$a, 'UTC', 'A Legal', '1'], [$b, 'Asia/Tehran', 'B Legal', '0']] as [$id, $timezone, $legal, $pricing]) {
        storeRuntimeSetting($id, SettingNameEnum::TIMEZONE->value, $timezone);
        storeRuntimeSetting($id, SettingNameEnum::STORE_LEGAL_NAME->value, $legal);
        storeRuntimeSetting($id, SettingNameEnum::PRICES_INCLUDE_TAX->value, $pricing);
    }

    $context = app(TenantContext::class);
    $aPrefix = 't'.$a.':';
    $bPrefix = 't'.$b.':';

    // Warm every store-dependent read under A.
    $context->forTenant($a);
    app(AppliesStoreTimezone::class)->__invoke();
    app(AppliesStoreIdentity::class)->__invoke();
    app(ResolvesPricingMode::class)->pricesIncludeTax();
    app(FxSettingsResolverInterface::class)->settings();
    PicksTranslation::defaultLanguageId();

    expect(Cache::get($aPrefix.AppliesStoreTimezone::CACHE_KEY))->toBe('UTC')
        ->and(Cache::get($aPrefix.AppliesStoreIdentity::CACHE_KEY))->toMatchArray(['legal_name' => 'A Legal'])
        ->and(Cache::get($aPrefix.ResolvesPricingMode::CACHE_KEY))->toBe('1')
        ->and(Cache::has($bPrefix.AppliesStoreTimezone::CACHE_KEY))->toBeFalse();

    // The same reads under B must miss A's entries entirely.
    $context->forTenant($b);
    app(AppliesStoreTimezone::class)->__invoke();
    $identityB = app(AppliesStoreIdentity::class)->__invoke();
    $pricingB = app(ResolvesPricingMode::class)->pricesIncludeTax();
    app(FxSettingsResolverInterface::class)->settings();
    PicksTranslation::defaultLanguageId();

    expect(config('app.timezone'))->toBe('Asia/Tehran')
        ->and($identityB['legal_name'])->toBe('B Legal')
        ->and($pricingB)->toBeFalse()
        ->and(Cache::get($aPrefix.AppliesStoreTimezone::CACHE_KEY))->toBe('UTC')
        ->and(Cache::get($aPrefix.AppliesStoreIdentity::CACHE_KEY))->toMatchArray(['legal_name' => 'A Legal'])
        ->and(Cache::get($aPrefix.ResolvesPricingMode::CACHE_KEY))->toBe('1')
        ->and(Cache::get($bPrefix.AppliesStoreTimezone::CACHE_KEY))->toBe('Asia/Tehran')
        ->and(Cache::get($bPrefix.AppliesStoreIdentity::CACHE_KEY))->toMatchArray(['legal_name' => 'B Legal'])
        ->and(Cache::get($bPrefix.ResolvesPricingMode::CACHE_KEY))->toBe('0')
        ->and(Cache::has($aPrefix.ResolvesFxSettings::CACHE_KEY))->toBeTrue()
        ->and(Cache::has($bPrefix.ResolvesFxSettings::CACHE_KEY))->toBeTrue()
        ->and(Cache::has($aPrefix.'core.language.default_id'))->toBeTrue()
        ->and(Cache::has($bPrefix.'core.language.default_id'))->toBeTrue();
});

it('invalidates every store\'s derived FX cache when a platform value changes', function () {
    storeRuntimeSeedPlatform();
    $a = (int) $this->createTenant('acme')->getKey();
    $b = (int) $this->createTenant('other')->getKey();
    $context = app(TenantContext::class);
    $fx = app(FxSettingsResolverInterface::class);

    $context->forTenant($a);
    expect($fx->settings()['provider'])->toBe('openexchangerates');

    $context->forTenant($b);
    expect($fx->settings()['provider'])->toBe('openexchangerates')
        ->and(Cache::has('t'.$b.':'.ResolvesFxSettings::CACHE_KEY))->toBeTrue();

    // A platform write invalidates only the unprefixed resolver cache;
    // the generation carried by the tenant-keyed aggregate does the rest.
    DB::table(SettingSchema::TABLE)
        ->whereNull(TenantColumn::TENANT_ID)
        ->where(SettingSchema::NAME, PlatformSettingsInterface::FX_PROVIDER)
        ->update([SettingSchema::VALUE => 'fixer', SettingSchema::UPDATED_AT => now()]);
    app(PlatformSettings::class)->forget(PlatformSettingsInterface::FX_PROVIDER);

    expect($fx->settings()['provider'])->toBe('fixer');

    $context->forTenant($a);
    expect($fx->settings()['provider'])->toBe('fixer');
});

it('applies platform settings inside a store request without entering central mode', function () {
    storeRuntimeSeedPlatform();
    $tenant = $this->createTenant('acme', 'shop-acme.test');
    $tenantId = (int) $tenant->getKey();
    storeRuntimeSetting($tenantId, SettingNameEnum::TIMEZONE->value, 'Europe/Berlin');
    storeRuntimeSetting($tenantId, SettingNameEnum::STORE_LEGAL_NAME->value, 'Acme GmbH');

    $this->productionLikeTenancy();
    Route::middleware('api')->get('/api/v1/tenant-probe/platform-setting', function () {
        $context = app(TenantContext::class);

        return [
            'tenant_id' => $context->tenantId(),
            'mode' => $context->mode(),
            'central_operation' => $context->centralOperation(),
            'provider' => app(PlatformSettingsInterface::class)->get(PlatformSettingsInterface::FX_PROVIDER),
        ];
    })->name('api.tenant.public.probe.platform-setting');

    $response = $this->getJson($this->onHost('shop-acme.test', '/api/v1/tenant-probe/platform-setting'))
        ->assertOk()
        ->assertJsonPath('tenant_id', $tenantId)
        ->assertJsonPath('mode', TenantContext::MODE_TENANT)
        ->assertJsonPath('provider', 'openexchangerates');

    expect($response->json('central_operation'))->toBeNull()
        // The per-request listener applied the store runtime before the
        // controller ran, from platform rows via the resolver.
        ->and(config('app.timezone'))->toBe('Europe/Berlin')
        ->and(config('store.legal_name'))->toBe('Acme GmbH')
        ->and(config('store.platform_identity'))->toBe(['name' => 'Baxela', 'url' => 'https://baxela.test'])
        ->and(Cache::has(PlatformSettings::cacheKey(PlatformSettingsInterface::FX_PROVIDER)))->toBeTrue()
        ->and(Cache::has('t'.$tenantId.':'.ResolvesFxSettings::CACHE_KEY))->toBeTrue();
});

it('fails closed when a platform setting is missing, malformed or not allowlisted', function () {
    $name = PlatformSettingsInterface::FX_PROVIDER;
    $resolver = app(PlatformSettingsInterface::class);

    try {
        $resolver->get($name);
        $this->fail('Expected PlatformSettingMissing for an absent platform row.');
    } catch (PlatformSettingMissing $e) {
        expect($e->code)->toBe('core.platform_setting.missing')
            ->and($e->httpStatus)->toBe(500)
            ->and($e->meta['setting'])->toBe($name);
    }

    // A store row with the same name is never a substitute.
    $tenantId = (int) $this->createTenant('acme')->getKey();
    storeRuntimeSetting($tenantId, $name, 'store-provider');
    expect(fn () => $resolver->get($name))->toThrow(PlatformSettingMissing::class);

    // An empty / non-JSON / structurally invalid row is a config defect.
    storeRuntimePlatform($name, '');
    expect(fn () => $resolver->get($name))->toThrow(PlatformSettingMissing::class);

    storeRuntimePlatform(PlatformSettingsInterface::PLATFORM_IDENTITY, '{not json');
    expect(fn () => $resolver->get(PlatformSettingsInterface::PLATFORM_IDENTITY))
        ->toThrow(PlatformSettingMissing::class);

    expect(fn () => $resolver->get(SettingNameEnum::STORE_LEGAL_NAME->value))
        ->toThrow(PlatformSettingMissing::class);
});

it('applies and restores store runtime between queued jobs for two stores', function () {
    storeRuntimeSeedPlatform();
    Language::factory()->create(['code' => 'en', 'is_active' => true, 'is_default' => true]);

    $a = (int) $this->createTenant('acme')->getKey();
    $b = (int) $this->createTenant('other')->getKey();
    storeRuntimeSetting($a, SettingNameEnum::TIMEZONE->value, 'UTC');
    storeRuntimeSetting($a, SettingNameEnum::STORE_LEGAL_NAME->value, 'A Legal');
    storeRuntimeSetting($b, SettingNameEnum::TIMEZONE->value, 'Asia/Tehran');
    storeRuntimeSetting($b, SettingNameEnum::STORE_LEGAL_NAME->value, 'B Legal');

    $context = app(TenantContext::class);
    $runtime = app(StoreRuntimeInterface::class);
    $context->forTenant($a);
    $runtime->apply($a);

    $outer = [
        'app_timezone' => config('app.timezone'),
        'php_timezone' => date_default_timezone_get(),
        'locale' => app()->getLocale(),
        'legal_name' => config('store.legal_name'),
        'supported_locales' => SupportedLocales::current(),
    ];

    // Two jobs for B in sequence: each applies B's runtime from scratch,
    // and the enclosing A runtime is restored after every attempt.
    foreach (['b-first', 'b-second'] as $label) {
        $context->runForTenant($b, function () use ($label): void {
            dispatch(new RecordsStoreRuntimeJob($label));
        });

        expect(RecordsStoreRuntimeJob::$observed)->toHaveCount($label === 'b-first' ? 1 : 2)
            ->and(RecordsStoreRuntimeJob::$observed[count(RecordsStoreRuntimeJob::$observed) - 1])
            ->toMatchArray([
                'label' => $label,
                'tenant_id' => $b,
                'mode' => TenantContext::MODE_TENANT,
                'app_timezone' => 'Asia/Tehran',
                'legal_name' => 'B Legal',
                'supported_locales' => ['en'],
            ])
            ->and(config('app.timezone'))->toBe($outer['app_timezone'])
            ->and(date_default_timezone_get())->toBe($outer['php_timezone'])
            ->and(config('store.legal_name'))->toBe($outer['legal_name'])
            ->and($context->tenantId())->toBe($a);
    }

    // A real worker also restores the captured runtime for a job whose
    // payload grants no tenant (INACTIVE) and for a central operation.
    config(['queue.default' => 'database']);
    app(CentralOperationRegistry::class)->register(ProbeOperation::class, [
        'handler' => [ProbeCentralCaller::class, 'execute'],
        'callers' => [ProbeCentralCaller::class],
        'jobs' => [RecordsContextJob::class],
    ]);

    $context->deactivate();
    dispatch(new RecordsStoreRuntimeJob('inactive'));

    $context->runCentral(ProbeOperation::class, function (): void {
        dispatch(new RecordsContextJob('central', invoke: new ProbeOperation('from-job')));
    });

    // What the worker looks like between jobs: A's runtime again.
    $context->forTenant($a);
    $runtime->apply($a);

    Artisan::call('queue:work', ['--once' => true, '--stop-when-empty' => true]);
    Artisan::call('queue:work', ['--once' => true, '--stop-when-empty' => true]);

    $inactive = collect(RecordsStoreRuntimeJob::$observed)->firstWhere('label', 'inactive');
    expect($inactive['tenant_id'])->toBeNull()
        ->and($inactive['mode'])->toBe(TenantContext::MODE_INACTIVE)
        ->and($inactive['app_timezone'])->toBe($outer['app_timezone'])
        ->and(ProbeCentralCaller::$observed[0]['mode'])->toBe(TenantContext::MODE_CENTRAL)
        ->and(config('app.timezone'))->toBe($outer['app_timezone'])
        ->and(config('store.legal_name'))->toBe($outer['legal_name'])
        ->and($context->tenantId())->toBe($a)
        ->and(SupportedLocales::current())->toBe($outer['supported_locales']);
});
