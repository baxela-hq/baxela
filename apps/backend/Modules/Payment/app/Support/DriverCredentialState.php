<?php

namespace Modules\Payment\Support;

use Modules\Core\Contracts\Gateways\Payment\ConfigurableDriver;
use Modules\Core\Contracts\Gateways\Payment\PaymentCredentialResolverInterface;
use Modules\Core\Contracts\Gateways\Payment\PaymentDriverInterface;
use Modules\Payment\Exceptions\PaymentException;

/**
 * The canonical per-driver credential state the admin API returns —
 * key resolution states plus an is_configured answer derived from those
 * SAME resolver states, never values. Single source so the listing and
 * the mutation responses can never disagree (marketplace P5.2 rev 3.1)
 * — and so a mutation response is correct immediately, without waiting
 * for the next request's credential middleware pass to rewrite config.
 */
class DriverCredentialState
{
    public function __construct(
        protected PaymentCredentialResolverInterface $resolver,
    ) {}

    /**
     * @return array<string, mixed>
     *
     * @throws PaymentException when the driver is not configurable
     */
    public function build(string $name, PaymentDriverInterface $driver): array
    {
        if (! $driver instanceof ConfigurableDriver) {
            throw PaymentException::credentialsDriverUnknown($name);
        }

        $keys = [];

        foreach ($driver->credentialKeys() as $key) {
            $keys[$key] = $this->resolver->stateFor($name, $key);
        }

        // Same rule as PaymentDriverManager::isConfigured(): every
        // declared key must resolve (stored or env) — an empty
        // declaration counts as configured.
        $configured = $keys === []
            || collect($keys)->every(fn (string $state): bool => in_array($state, [
                PaymentCredentialResolverInterface::KEY_STATE_STORED,
                PaymentCredentialResolverInterface::KEY_STATE_ENV,
            ], true));

        return [
            'driver' => $name,
            'keys' => $keys,
            'is_configured' => $configured,
        ];
    }
}
