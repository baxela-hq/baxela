<?php

namespace Modules\Tenant\Operations;

use Modules\Core\Support\Tenancy\CentralOperation;

/** Releases a store's domain and starts its cooling-off window (§9.7). */
final class ReleaseTenantDomainOperation implements CentralOperation
{
    public function __construct(public readonly int $tenantId, public readonly int $domainId) {}

    public function id(): string
    {
        return 'tenant-domain.release';
    }
}
