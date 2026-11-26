<?php

namespace Modules\Tenant\Operations;

use Modules\Core\Support\Tenancy\CentralOperation;

/** Soft-deletes a store: the storefront closes and its domains stop resolving. */
final class DeleteTenantOperation implements CentralOperation
{
    public function __construct(public readonly int $tenantId) {}

    public function id(): string
    {
        return 'tenant.delete';
    }
}
