<?php

namespace Modules\Tenant\Actions\Admin\Tenant;

use LogicException;
use Modules\Core\Support\Tenancy\CentralOperations;
use Modules\Tenant\Exceptions\Tenant\DeletionFailedException;
use Modules\Tenant\Models\Tenant;
use Modules\Tenant\Operations\DeleteTenantOperation;
use Throwable;

/**
 * Soft-deletes a store (multi-tenancy §9.7): the storefront closes and its
 * domains stop resolving, exactly like suspension; nothing is erased.
 * Permanent deletion is a later, separate decision.
 *
 * handle() is the operation's only authorized caller; execute() is its
 * registered handler (§6.5).
 */
class DeleteTenantAction
{
    public function __construct(private readonly CentralOperations $central) {}

    public function handle(string $id): Tenant
    {
        $result = $this->central->run(new DeleteTenantOperation((int) $id), static::class);

        return $result instanceof Tenant
            ? $result
            : throw new LogicException('The tenant.delete operation did not return a tenant.');
    }

    /** Runs inside CentralOperations::run(). */
    public function execute(DeleteTenantOperation $operation): Tenant
    {
        try {
            $tenant = Tenant::query()->findOrFail($operation->tenantId);

            $tenant->delete();

            return $tenant;
        } catch (Throwable $e) {
            report($e);

            throw new DeletionFailedException;
        }
    }
}
