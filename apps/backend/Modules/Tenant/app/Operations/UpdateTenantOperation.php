<?php

namespace Modules\Tenant\Operations;

use Modules\Core\Support\Tenancy\CentralOperation;

/** Updates a store's name and contact address; its slug never changes. */
final class UpdateTenantOperation implements CentralOperation
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(public readonly int $tenantId, public readonly array $data) {}

    public function id(): string
    {
        return 'tenant.update';
    }
}
