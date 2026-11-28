<?php

namespace Modules\Payment\Support;

use Modules\Core\Contracts\Gateways\Payment\PaymentConfiguration;
use Modules\Core\Contracts\Gateways\Payment\PaymentConfigurationResolverInterface;
use Modules\Core\Exceptions\PaymentConfigurationMissing;
use Modules\Payment\Models\PaymentDriverCredential;
use Modules\Payment\Schemas\PaymentDriverCredential\PaymentDriverCredentialSchema;

/**
 * The per-tenant payment configuration resolver (multi-tenancy §6.3, A2).
 * One store's configuration for one driver — a disabled or missing
 * configuration refuses BEFORE any credential use: no other tenant's
 * credentials, no platform default, no env fallback in production
 * (rows 25/42). The env baseline applies in local/testing only, where
 * the seeders' drivers are exercised without stored rows.
 */
class ResolvesPaymentConfiguration implements PaymentConfigurationResolverInterface
{
    public function __construct(private readonly ResolvesPaymentCredentials $credentials) {}

    /**
     * @throws PaymentConfigurationMissing
     */
    public function get(string $driver): PaymentConfiguration
    {
        $version = $this->credentials->credentialVersion($driver);
        $enabled = ! $this->credentials->driverDisabled($driver);

        if ($this->isProductionLike() && ! $this->hasStoredConfiguration($driver)) {
            // Production fail-closed: no stored rows means no usable
            // configuration — the env baseline is a development
            // convenience, never a production source (§6.3).
            throw new PaymentConfigurationMissing($driver, 'no_stored_configuration');
        }

        if (! $enabled && $this->hasStoredConfiguration($driver)) {
            // Disabled means unusable (row 42): direct client/driver
            // construction cannot use stored credentials either.
            throw new PaymentConfigurationMissing($driver, 'disabled');
        }

        return new PaymentConfiguration(
            driver: $driver,
            credential_version: $version,
            enabled: $enabled,
        );
    }

    /** The store holds at least one credential row for the driver. */
    private function hasStoredConfiguration(string $driver): bool
    {
        return PaymentDriverCredential::query()
            ->where(PaymentDriverCredentialSchema::DRIVER, $driver)
            ->exists();
    }

    private function isProductionLike(): bool
    {
        return app()->environment('production');
    }
}
