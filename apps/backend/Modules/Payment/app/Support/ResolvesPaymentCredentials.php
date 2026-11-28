<?php

namespace Modules\Payment\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Modules\Core\Contracts\Gateways\Payment\PaymentCredentialResolverInterface;
use Modules\Core\Exceptions\TenantContextMissingException;
use Modules\Core\Support\Tenancy\TenantContext;
use Modules\Payment\Models\PaymentDriverCredential;
use Modules\Payment\Schemas\PaymentDriverCredential\PaymentDriverCredentialSchema;
use Throwable;

/**
 * The payment credential resolver (marketplace P5.2 D2; multi-tenancy
 * §6.3): stored rows are read through the tenant scope and cached
 * ENCRYPTED per (tenant, driver) with the driver's `credential_version`
 * in the cache key — plaintext never cached, decryption per call. A
 * rotated credential bumps the version in the same transaction as the
 * write (UpdateDriverCredentialsAction), so the next read lands on a
 * fresh key and no other driver's cache entry is touched (F1, row 32).
 *
 * The env fallback reads an immutable process-lifetime baseline — a
 * snapshot of config('payment') taken on this class's first call — never
 * the live repository. refreshEnvBaseline() exists for controlled
 * situations (tests, deliberate env reloads) only.
 */
class ResolvesPaymentCredentials implements PaymentCredentialResolverInterface
{
    public const string CACHE_PREFIX = 'payment.credentials:';

    /** Snapshot of config('payment') — the immutable env fallback source.
     *
     * @var array<string, mixed>|null
     */
    protected static ?array $baseline = null;

    /**
     * @return array{value: ?string, state: string}
     */
    protected function resolve(string $driver, string $key): array
    {
        $stored = $this->storedMap($driver)[$key] ?? null;

        if (is_string($stored) && $stored !== '') {
            try {
                return ['value' => Crypt::decryptString($stored), 'state' => self::KEY_STATE_STORED];
            } catch (Throwable) {
                // APP_KEY rotation signature: fail closed — the env value
                // is deliberately NOT consulted (review F2).
                return ['value' => null, 'state' => self::KEY_STATE_UNDECRYPTABLE];
            }
        }

        $env = $this->baseline()[$driver][$key] ?? null;

        // Legacy manager truthiness semantics: a falsy env value ('' or
        // '0') is an unconfigured credential, exactly like the old
        // `! config(...)` check (review F8).
        if (! $env) {
            return ['value' => null, 'state' => self::KEY_STATE_NONE];
        }

        return ['value' => (string) $env, 'state' => self::KEY_STATE_ENV];
    }

    public function credential(string $driver, string $key): ?string
    {
        return $this->resolve($driver, $key)['value'];
    }

    public function stateFor(string $driver, string $key): string
    {
        return $this->resolve($driver, $key)['state'];
    }

    /**
     * The driver's rows are disabled-or-enabled as a set (the admin write
     * flips them together, §6.3): a disabled driver answers UNUSABLE for
     * every key (row 42 — direct client/driver construction cannot use
     * stored credentials either).
     */
    public function driverDisabled(string $driver): bool
    {
        $rows = $this->storedRows($driver);

        if ($rows === []) {
            return false;
        }

        foreach ($rows as $row) {
            if ((bool) $row->{PaymentDriverCredentialSchema::ENABLED}) {
                return false;
            }
        }

        return true;
    }

    /**
     * The driver's current rotation counter, read UNCACHED so a bump takes
     * effect on the very next resolve (the credential map itself is
     * version-keyed and needs no invalidation).
     */
    public function credentialVersion(string $driver): int
    {
        try {
            // 0 = no rows yet — distinct from the first write's version 1,
            // so a pre-configuration cached entry is never read
            // post-configuration. The credentials table may not exist yet
            // (installer flows, unit tests): version 0 there too.
            return (int) PaymentDriverCredential::query()
                ->where(PaymentDriverCredentialSchema::DRIVER, $driver)
                ->max(PaymentDriverCredentialSchema::CREDENTIAL_VERSION);
        } catch (TenantContextMissingException $e) {
            throw $e;
        } catch (Throwable) {
            return 0;
        }
    }

    public function invalidate(): void
    {
        // Version-keyed caches need no invalidation: a bump rekeys, old
        // entries expire untouched, and no other driver's entry is
        // affected (§6.3 F1). Kept for the contract; clearing the
        // per-driver entries would require knowing versions, which are
        // read fresh by design.
    }

    public function refreshEnvBaseline(): void
    {
        static::$baseline = null;
    }

    /**
     * The stored credential rows of ONE driver, read through the tenant
     * scope and cached per (tenant, driver, credential_version).
     *
     * @return list<PaymentDriverCredential>
     */
    protected function storedRows(string $driver): array
    {
        $context = app(TenantContext::class);

        if ($context->mode() !== TenantContext::MODE_TENANT || $context->tenantId() === null) {
            // Fail closed (§4.3): credentials without a store context are
            // a programming/deployment bug, never an implicit grant.
            throw new TenantContextMissingException;
        }

        $version = $this->credentialVersion($driver);
        $cacheKey = $context->cachePrefix().self::CACHE_PREFIX.$driver.':'.$version;

        try {
            return Cache::remember($cacheKey, now()->addMinutes(15), function () use ($driver): array {
                return PaymentDriverCredential::query()
                    ->where(PaymentDriverCredentialSchema::DRIVER, $driver)
                    ->get()
                    ->all();
            });
        } catch (TenantContextMissingException $e) {
            // F38/row 45: a tenancy exception is rethrown, never turned
            // into a platform default.
            throw $e;
        } catch (Throwable) {
            // The credentials table is not migrated yet (installer flows,
            // fresh test runs): fail closed — every credential falls back
            // to env, nothing stored exists.
            return [];
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function storedMap(string $driver): array
    {
        $map = [];

        foreach ($this->storedRows($driver) as $row) {
            $map[$row->{PaymentDriverCredentialSchema::KEY}] = $row->getRawOriginal(PaymentDriverCredentialSchema::VALUE);
        }

        return $map;
    }

    /**
     * @return array<string, mixed>
     */
    protected function baseline(): array
    {
        if (static::$baseline === null) {
            static::$baseline = (array) config('payment');
        }

        return static::$baseline;
    }
}
