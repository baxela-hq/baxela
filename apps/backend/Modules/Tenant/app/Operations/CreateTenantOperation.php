<?php

namespace Modules\Tenant\Operations;

use Modules\Core\Support\Tenancy\CentralOperation;

/** Creates a store. Platform administration, run through CentralOperations (multi-tenancy §6.5). */
final class CreateTenantOperation implements CentralOperation
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(public readonly array $data) {}

    public function id(): string
    {
        return 'tenant.create';
    }
}
