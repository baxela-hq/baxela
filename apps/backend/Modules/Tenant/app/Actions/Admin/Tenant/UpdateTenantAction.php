<?php

namespace Modules\Tenant\Actions\Admin\Tenant;

use LogicException;
use Modules\Core\Support\Tenancy\CentralOperations;
use Modules\Tenant\Exceptions\Tenant\UpdateFailedException;
use Modules\Tenant\Models\Tenant;
use Modules\Tenant\Operations\UpdateTenantOperation;
use Modules\Tenant\Schemas\Tenant\TenantSchema;
use Throwable;

/**
 * Updates a store's name and contact address. Its slug never changes: the
 * slug is the store's subdomain identity.
 *
 * handle() is the operation's only authorized caller; execute() is its
 * registered handler (multi-tenancy §6.5).
 */
class UpdateTenantAction
{
    public function __construct(private readonly CentralOperations $central) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(string $id, array $data): Tenant
    {
        $result = $this->central->run(new UpdateTenantOperation((int) $id, $data), static::class);

        return $result instanceof Tenant
            ? $result
            : throw new LogicException('The tenant.update operation did not return a tenant.');
    }

    /** Runs inside CentralOperations::run(). */
    public function execute(UpdateTenantOperation $operation): Tenant
    {
        try {
            $tenant = Tenant::query()->findOrFail($operation->tenantId);

            $tenant->update([
                TenantSchema::NAME => $operation->data[TenantSchema::NAME],
                TenantSchema::EMAIL => $operation->data[TenantSchema::EMAIL],
            ]);

            return $tenant;
        } catch (Throwable $e) {
            report($e);

            throw new UpdateFailedException;
        }
    }
}
