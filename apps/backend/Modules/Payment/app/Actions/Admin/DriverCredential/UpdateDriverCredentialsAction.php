<?php

namespace Modules\Payment\Actions\Admin\DriverCredential;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\Events\Payment\PaymentCredentialsUpdatedEvent;
use Modules\Core\Contracts\Gateways\Payment\ConfigurableDriver;
use Modules\Core\Contracts\Gateways\Payment\PaymentCredentialResolverInterface;
use Modules\Payment\Exceptions\PaymentException;
use Modules\Payment\Gateways\PaymentDriverManager;
use Modules\Payment\Gateways\PaypalClient;
use Modules\Payment\Models\PaymentDriverCredential;
use Modules\Payment\Schemas\PaymentDriverCredential\PaymentDriverCredentialSchema;
use Modules\Payment\Support\DriverCredentialState;

/**
 * Committed credential mutations (marketplace P5.2 F3/F4/F5): validate
 * everything first, wrap every write in one transaction, and only after
 * the commit — invalidate the resolver cache, flush PayPal's cached
 * OAuth token when its credentials changed, dispatch the audit event.
 * A rolled-back mutation leaves no rows, no invalidation, no event.
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
            foreach ($credentials as $key => $value) {
                PaymentDriverCredential::query()->updateOrCreate(
                    [
                        PaymentDriverCredentialSchema::DRIVER => $name,
                        PaymentDriverCredentialSchema::KEY => $key,
                    ],
                    [PaymentDriverCredentialSchema::VALUE => $value],
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
            return PaymentDriverCredential::query()
                ->where(PaymentDriverCredentialSchema::DRIVER, $name)
                ->where(PaymentDriverCredentialSchema::KEY, $key)
                ->delete();
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
        $this->resolver->invalidate();

        if ($name === 'paypal') {
            Cache::forget(PaypalClient::ACCESS_TOKEN_CACHE_KEY);
        }

        event(PaymentCredentialsUpdatedEvent::fill([
            'driver' => $name,
            'keys' => $keys,
            'action' => $action,
        ]));
    }
}
