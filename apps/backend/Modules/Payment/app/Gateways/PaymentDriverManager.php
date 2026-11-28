<?php

namespace Modules\Payment\Gateways;

use Modules\Core\Contracts\Gateways\Payment\ConfigurableDriver;
use Modules\Core\Contracts\Gateways\Payment\PaymentDriverInterface;
use Modules\Core\Contracts\Gateways\Payment\RefundableDriver;
use Modules\Payment\Exceptions\PaymentException;

class PaymentDriverManager
{
    /**
     * @throws PaymentException
     */
    public function forName(string $name): PaymentDriverInterface
    {
        $driver = config("payment.drivers.{$name}");

        if (is_null($driver)) {
            file_put_contents('/tmp/fn.log', 'MISS name='.var_export($name, true)."\n", FILE_APPEND);
            throw PaymentException::methodNotSupported();
        }
        file_put_contents('/tmp/fn.log', 'HIT name='.var_export($name, true)."\n", FILE_APPEND);

        return app($driver);
    }

    public function isRegistered(string $driver): bool
    {
        return array_key_exists($driver, (array) config('payment.drivers'));
    }

    /**
     * Whether the driver's refund() is wired. Capability is declared by
     * implementing RefundableDriver — next to the behavior itself, so the
     * two cannot drift apart.
     */
    public function supportsRefund(string $driver): bool
    {
        return $this->isRegistered($driver)
            && $this->forName($driver) instanceof RefundableDriver;
    }

    /**
     * Whether the driver carries the credentials its full payment
     * lifecycle needs — checkout initiation and webhook verification
     * alike, per its own ConfigurableDriver declaration (credentialKeys()
     * IS the required-for-lifecycle set: every declared key is required,
     * webhook secrets included — marketplace P5.2 review F7). False for
     * unregistered drivers and for registered ones that declare no
     * contract (fail closed); a driver declaring no keys counts as
     * configured.
     *
     * The check reads the context-scoped resolver, deliberately: the
     * active tenant's stored rows (with the env fallback in
     * local/testing) are the effective credential state on both call
     * surfaces (user checkout method listing, admin methods resource).
     * Legacy truthiness semantics preserved
     * verbatim — '' and '0' count as unconfigured. Only this policy
     * answer is boolean: unexpected driver-resolution failures
     * propagate, never silently become "unconfigured".
     */
    public function isConfigured(string $driver): bool
    {
        if (! $this->isRegistered($driver)) {
            return false;
        }

        $instance = $this->forName($driver);

        if (! $instance instanceof ConfigurableDriver) {
            return false;
        }

        $keys = $instance->credentialKeys();

        if ($keys === []) {
            return true;
        }

        // A disabled driver is unconfigured for every surface (multi-
        // tenancy §6.3, row 42): direct construction cannot use it either.
        if (app(\Modules\Payment\Support\ResolvesPaymentCredentials::class)->driverDisabled($driver)) {
            return false;
        }

        foreach ($keys as $key) {
            // The resolver reads the active tenant's scoped rows with the
            // env fallback in local/testing — config() is no longer the
            // credential state (§6.3).
            if (! app(\Modules\Core\Contracts\Gateways\Payment\PaymentCredentialResolverInterface::class)->credential($driver, $key)) {
                return false;
            }
        }

        return true;
    }
}
