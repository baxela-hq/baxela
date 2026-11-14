<?php

namespace Modules\Payment\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Modules\Core\Contracts\Gateways\Payment\PaymentCredentialResolverInterface;
use Modules\Payment\Models\PaymentDriverCredential;
use Modules\Payment\Schemas\PaymentDriverCredential\PaymentDriverCredentialSchema;
use Throwable;

/**
 * The payment credential resolver (marketplace P5.2 D2): stored rows are
 * cached ENCRYPTED (15-minute TTL, plaintext never cached) and decrypted
 * per call; the env fallback reads an immutable process-lifetime
 * baseline — a snapshot of config('payment') taken on this class's first
 * call — never the live repository.
 *
 * Why the baseline: under Octane the config repository persists per
 * worker, and ApplyDatabaseCredentials writes resolved credentials into
 * it on every request. Resolving against the live repository would let a
 * deleted database credential be "resolved" from a previous request's
 * write instead of the true environment value (review F1). The snapshot
 * initializes inside the middleware's first resolver call — before that
 * middleware writes anything in this process — and the middleware is the
 * sole writer of credential config keys, so the baseline cannot be
 * contaminated. refreshEnvBaseline() exists for controlled situations
 * (tests, deliberate env reloads) only.
 */
class ResolvesPaymentCredentials implements PaymentCredentialResolverInterface
{
    public const string CACHE_KEY = 'payment.credentials';

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
        $stored = $this->storedMap()[$driver][$key] ?? null;

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

    public function invalidate(): void
    {
        try {
            Cache::forget(self::CACHE_KEY);
        } catch (Throwable $e) {
            // A failed invalidation must not fail the committed mutation;
            // worst case is the documented 15-minute staleness bound.
            report($e);
        }
    }

    public function refreshEnvBaseline(): void
    {
        static::$baseline = null;
    }

    /**
     * The stored credential map, keyed driver → key → raw ciphertext.
     * Encrypted values are cached raw; decryption happens per call.
     *
     * @return array<string, array<string, string>>
     */
    protected function storedMap(): array
    {
        try {
            return Cache::remember(self::CACHE_KEY, now()->addMinutes(15), function (): array {
                return PaymentDriverCredential::query()
                    ->get()
                    ->groupBy(PaymentDriverCredentialSchema::DRIVER)
                    ->mapWithKeys(fn ($rows, $driver) => [
                        $driver => $rows
                            ->mapWithKeys(fn ($row) => [
                                $row->{PaymentDriverCredentialSchema::KEY} => $row->getRawOriginal(PaymentDriverCredentialSchema::VALUE),
                            ])
                            ->all(),
                    ])
                    ->all();
            });
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
    protected function baseline(): array
    {
        if (static::$baseline === null) {
            static::$baseline = (array) config('payment');
        }

        return static::$baseline;
    }
}
