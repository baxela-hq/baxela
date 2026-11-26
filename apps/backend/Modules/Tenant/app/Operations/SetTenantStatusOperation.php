<?php

namespace Modules\Tenant\Operations;

use Modules\Core\Support\Tenancy\CentralOperation;

/** Suspends or reactivates a store. */
final class SetTenantStatusOperation implements CentralOperation
{
    public function __construct(public readonly int $tenantId, public readonly string $status) {}

    public function id(): string
    {
        return 'tenant.set-status';
    }
}
