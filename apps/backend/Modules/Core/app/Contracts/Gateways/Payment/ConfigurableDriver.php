<?php

namespace Modules\Core\Contracts\Gateways\Payment;

/**
 * Declares the config keys a driver needs to operate, so the platform
 * can tell "installed" from "usable" without a hardcoded per-gateway
 * catalog: each key is relative to the driver's own block
 * (payment.{driver}.) and must be truthy for isConfigured() to pass —
 * covering the full payment lifecycle, webhook verification included,
 * not merely checkout initiation. Drivers that need no credentials
 * (manual settlement) declare an empty list. The manager owns value
 * resolution; keys are trusted relative names, never arbitrary config
 * paths — a tenant-aware credential resolver can replace the lookup
 * later without touching a single driver.
 */
interface ConfigurableDriver extends PaymentDriverInterface
{
    /**
     * @return list<string>
     */
    public function credentialKeys(): array;
}
