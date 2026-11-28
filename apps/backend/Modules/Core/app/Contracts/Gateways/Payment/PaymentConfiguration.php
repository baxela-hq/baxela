<?php

namespace Modules\Core\Contracts\Gateways\Payment;

/**
 * One store's configuration for one payment driver (multi-tenancy §6.3):
 * credentials are decrypted ON DEMAND — never held in the DTO, never
 * logged, never serialized into responses or exception context (F21's
 * redaction covers secret/token/password/key keys).
 */
final class PaymentConfiguration
{
    public function __construct(
        public readonly string $driver,
        /** The driver's current rotation counter — provider-facing caches key off it (§6.3 F1). */
        public readonly int $credential_version,
        /** True when the store's rows are enabled; a disabled driver is unusable (row 42). */
        public readonly bool $enabled,
    ) {}

    /**
     * The decrypted credential for one key — per-call decryption through
     * the active tenant's scoped rows; a key with no value answers null.
     */
    public function credential(string $key): ?string
    {
        return app(PaymentCredentialResolverInterface::class)
            ->credential($this->driver, $key);
    }
}
