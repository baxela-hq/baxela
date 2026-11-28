<?php

namespace Modules\Core\Contracts\Gateways\Payment;

use Modules\Core\Exceptions\PaymentConfigurationMissing;

/**
 * The per-tenant payment configuration resolver (multi-tenancy §6.3, A2).
 * Every client and driver reads its configuration through this contract —
 * never through config(), never through another tenant's rows.
 *
 * @throws PaymentConfigurationMissing — never falls back to another
 *                                     tenant or to platform defaults in
 *                                     production
 */
interface PaymentConfigurationResolverInterface
{
    public function get(string $driver): PaymentConfiguration;
}
