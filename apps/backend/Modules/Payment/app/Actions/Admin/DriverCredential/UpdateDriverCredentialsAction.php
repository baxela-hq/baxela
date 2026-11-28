<?php

namespace Modules\Payment\Actions\Admin\DriverCredential;

use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\Events\Payment\PaymentCredentialsUpdatedEvent;
use Modules\Core\Contracts\Gateways\Payment\ConfigurableDriver;
use Modules\Core\Contracts\Gateways\Payment\PaymentCredentialResolverInterface;
use Modules\Payment\Exceptions\PaymentException;
use Modules\Payment\Gateways\PaymentDriverManager;
use Modules\Payment\Models\PaymentDriverCredential;
use Modules\Payment\Schemas\PaymentDriverCredential\PaymentDriverCredentialSchema;
use Modules\Payment\Support\DriverCredentialState;

/**
 * Committed credential mutations (marketplace P5.2 F3/F4/F5): validate
 * everything first, wrap every write in one transaction, and only after
 * the commit — dispatch the audit event. Cache invalidation is
 * superseded by the credential_version bump (multi-tenancy §6.3 F1): the
 * version-keyed caches rekey on the next resolve. A rolled-back mutation
 * leaves no rows, no bump, no event.
 */
class UpdateDriverCredentialsAction
{
    public function __construct(
        protected PaymentDriverManager $manager,
        protected PaymentCredentialResolverInterface $resolver,
        protected DriverCredentialState $state,
    ) {}

    /**
     * Partial update (PATCH semantics): submitted keys are upserted,
     * omitted keys are preserved.
     *
     * @param  array<string, string>  $credentials  validated key => value pairs
     * @return array<string, mixed> the post-mutation credential state
     *
     * @throws PaymentException
     */
    public function handle(string $name, array $credentials): array
    {
        try {
            $driver = $this->manager->forName($name);
        } catch (PaymentException) {
            // An unregistered driver surfaces as the domain error, not the
            // generic method_not_supported (marketplace P5.2 D5).
            throw PaymentException::credentialsDriverUnknown($name);
        }

        if (! $driver instanceof ConfigurableDriver || $driver->credentialKeys() === []) {
            throw PaymentException::credentialsDriverUnknown($name);
        }

        $declared = $driver->credentialKeys();

        foreach (array_keys($credentials) as $key) {
            if (! in_array($key, $declared, true)) {
                throw PaymentException::credentialsKeyUnknown($name, $key);
            }
        }

        DB::transaction(function () use ($name, $credentials): void {
            // The driver's version is stamped in the SAME transaction as
            // the credential write (multi-tenancy §6.3), so no observer
            // can see new credentials paired with an old version (and
            // vice versa). A millisecond stamp is monotonic for every
            // write — surviving even the driver's full deletion — and
            // every row of the driver shares it. The version-keyed caches
            // rekey on the next resolve.
            $version = now()->getTimestampMs();

            foreach ($credentials as $key => $value) {
                PaymentDriverCredential::query()->updateOrCreate(
                    [
                        PaymentDriverCredentialSchema::DRIVER => $name,
                        PaymentDriverCredentialSchema::KEY => $key,
                    ],
                    [
                        PaymentDriverCredentialSchema::VALUE => $value,
                        PaymentDriverCredentialSchema::CREDENTIAL_VERSION => $version,
                    ],
                );
            }
        });

        $this->afterCommit($name, array_keys($credentials), 'patch');

        return $this->state->build($name, $this->manager->forName($name));
    }

    /**
     * Removes a stored credential — the DB override, not necessarily the
     * effective credential: env fallback applies again when present
     * (marketplace P5.2 F6). Idempotent; a no-op delete mutates nothing
     * and audits nothing.
     *
     * @return array<string, mixed> the post-mutation credential state
     *
     * @throws PaymentException
     */
    public function delete(string $name, string $key): array
    {
        try {
            $driver = $this->manager->forName($name);
        } catch (PaymentException) {
            // An unregistered driver surfaces as the domain error, not the
            // generic method_not_supported (marketplace P5.2 D5).
            throw PaymentException::credentialsDriverUnknown($name);
        }

        if (! $driver instanceof ConfigurableDriver || $driver->credentialKeys() === []) {
            throw PaymentException::credentialsDriverUnknown($name);
        }

        if (! in_array($key, $driver->credentialKeys(), true)) {
            throw PaymentException::credentialsKeyUnknown($name, $key);
        }

        $deleted = DB::transaction(function () use ($name, $key): int {
            // The version stamp is load-bearing on deletes too (multi-
            // tenancy §6.3): a deleted credential must stop resolving on
            // the next request, not when the stale cache entry expires.
            $version = now()->getTimestampMs();

            $deleted = PaymentDriverCredential::query()
                ->where(PaymentDriverCredentialSchema::DRIVER, $name)
                ->where(PaymentDriverCredentialSchema::KEY, $key)
                ->delete();

            if ($deleted > 0) {
                PaymentDriverCredential::query()
                    ->where(PaymentDriverCredentialSchema::DRIVER, $name)
                    ->update([PaymentDriverCredentialSchema::CREDENTIAL_VERSION => $version]);
            }

            return $deleted;
        });

        if ($deleted > 0) {
            $this->afterCommit($name, [$key], 'delete');
        }

        return $this->state->build($name, $this->manager->forName($name));
    }

    /**
     * Runs strictly after the transaction commits (review F4/F5): cache
     * invalidation, the PayPal OAuth token flush, and the audit event.
     */
    /** @param list<string> $keys */
    protected function afterCommit(string $name, array $keys, string $action): void
    {
        // Cache invalidation is superseded by the version bump (multi-
        // tenancy §6.3 F1): the credential map and the PayPal token cache
        // are keyed by (tenant, driver, credential_version), so the bump
        // in the write transaction rekeys every consumer — old entries
        // expire untouched and no other driver's entry is affected. The
        // audit event is the committed mutation's one after-effect.
        event(PaymentCredentialsUpdatedEvent::fill([
            'driver' => $name,
            'keys' => $keys,
            'action' => $action,
        ]));
    }
}
