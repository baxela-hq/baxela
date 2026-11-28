<?php

use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Modules\Core\Contracts\Gateways\Setting\PlatformSettingsInterface;
use Modules\Core\Exceptions\TenantContextMissingException;
use Modules\Core\Exceptions\TenantNotFoundException;
use Modules\Core\Models\Language;
use Modules\Core\Support\SupportedLocales;
use Modules\Core\Support\Tenancy\TenantContext;
use Modules\Setting\Models\Setting;
use Modules\Setting\Support\AppliesStoreIdentity;
use Modules\Setting\Support\AppliesStoreTimezone;
use Modules\Setting\Support\ResolvesFxSettings;
use Modules\Setting\Support\ResolvesPricingMode;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['cache.default' => 'array']);
    Cache::flush();
    SupportedLocales::flush();
    app(TenantContext::class)->deactivate();
});

it('rethrows tenancy failures from every F38 resolver catch site', function () {
    $context = app(TenantContext::class);
    $context->deactivate();

    // Setting and Language are both platform-tier models: their own tenant
    // scope intentionally allows inactive mode. Inject a tenancy failure
    // through a global scope so each resolver's catch (Throwable) is proven
    // to rethrow it instead of relabelling it a missing setting.
    $settingScopes = Setting::getAllGlobalScopes();
    Setting::addGlobalScope('tenancy-probe', static function (): never {
        throw new TenantContextMissingException;
    });

    try {
        expect(fn () => app(AppliesStoreTimezone::class)->__invoke())
            ->toThrow(TenantContextMissingException::class);
        expect(fn () => app(AppliesStoreIdentity::class)->__invoke())
            ->toThrow(TenantContextMissingException::class);
        expect(fn () => app(ResolvesPricingMode::class)->pricesIncludeTax())
            ->toThrow(TenantContextMissingException::class);
    } finally {
        Setting::setAllGlobalScopes($settingScopes);
    }

    $languageScopes = Language::getAllGlobalScopes();
    Language::addGlobalScope('tenancy-probe', static function (): never {
        throw new TenantContextMissingException;
    });

    try {
        expect(fn () => SupportedLocales::codes())
            ->toThrow(TenantContextMissingException::class);
    } finally {
        Language::setAllGlobalScopes($languageScopes);
    }

    // Three FX sites: inject the tenancy failure through the platform
    // contract so it reaches each catch without a real store query.
    $boom = new class implements PlatformSettingsInterface
    {
        public function get(string $name): mixed
        {
            throw new TenantContextMissingException;
        }

        public function has(string $name): bool
        {
            return false;
        }

        public function write(string $name, string $value): void {}

        public function forget(string $name): void {}
    };
    Cache::forget(ResolvesFxSettings::cacheKey());
    $fx = new ResolvesFxSettings($boom);

    expect(fn () => $fx->settings())->toThrow(TenantContextMissingException::class)
        ->and(fn () => $fx->lastSyncStatus())->toThrow(TenantContextMissingException::class);

    // The credential-decryption site: a tenancy exception escaping the
    // decrypt call must not be relabelled "undecryptable".
    $stored = new class implements PlatformSettingsInterface
    {
        public function get(string $name): mixed
        {
            return match ($name) {
                self::FX_AUTO_SYNC_ENABLED => true,
                self::FX_PROVIDER => 'openexchangerates',
                self::FX_API_KEY => 'stored-secret',
                self::FX_SYNC_INTERVAL_HOURS => 24,
                default => null,
            };
        }

        public function has(string $name): bool
        {
            return false;
        }

        public function write(string $name, string $value): void {}

        public function forget(string $name): void {}
    };

    $encrypter = Mockery::mock(Encrypter::class);
    $encrypter->shouldReceive('decryptString')->andThrow(new TenantContextMissingException);
    $originalEncrypter = app('encrypter');
    Crypt::swap($encrypter);
    Cache::forget(ResolvesFxSettings::cacheKey());

    try {
        expect(fn () => (new ResolvesFxSettings($stored))->settings())
            ->toThrow(TenantContextMissingException::class);
    } finally {
        Crypt::swap($originalEncrypter);
    }
});

it('rethrows the tenant-not-found family from the FX resolver', function () {
    $notFound = new class implements PlatformSettingsInterface
    {
        public function get(string $name): mixed
        {
            throw TenantNotFoundException::unresolved('probe');
        }

        public function has(string $name): bool
        {
            return false;
        }

        public function write(string $name, string $value): void {}

        public function forget(string $name): void {}
    };

    Cache::forget(ResolvesFxSettings::cacheKey());

    expect(fn () => (new ResolvesFxSettings($notFound))->settings())
        ->toThrow(TenantNotFoundException::class);
});
