<?php

namespace Modules\Tenant\Operations;

use Modules\Core\Support\Tenancy\CentralOperation;

/** Attaches a domain to a store. It stays pending — inert — until verified (§9.7). */
final class AttachTenantDomainOperation implements CentralOperation
{
    public function __construct(public readonly int $tenantId, public readonly string $domain, public readonly bool $isPrimary) {}

    public function id(): string
    {
        return 'tenant-domain.attach';
    }
}
