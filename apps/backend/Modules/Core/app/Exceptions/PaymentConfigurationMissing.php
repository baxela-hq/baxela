<?php

namespace Modules\Core\Exceptions;

use Throwable;

/**
 * A store's configuration for a payment driver is missing, malformed, or
 * disabled (multi-tenancy §6.3): the resolver refuses before any
 * credential use — no other tenant's credentials, no platform default,
 * no env fallback in production. Safe to expose (isSafe): the caller
 * learns only that THIS store lacks a usable configuration.
 */
class PaymentConfigurationMissing extends BaseException
{
    public function __construct(
        string $driver,
        ?string $reason = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            'core.payment.configuration_missing',
            422,
            ['driver' => $driver, ...($reason !== null ? ['reason' => $reason] : [])],
            true,
            $previous,
        );
    }
}
