<?php

namespace Modules\Tenant\Actions\Admin\Tenant;

use LogicException;
use Modules\Core\Support\Tenancy\CentralOperations;
use Modules\Tenant\Exceptions\Tenant\StatusChangeFailedException;
use Modules\Tenant\Models\Tenant;
use Modules\Tenant\Operations\SetTenantStatusOperation;
use Modules\Tenant\Schemas\Tenant\TenantSchema;
use Throwable;

/**
 * Suspends or reactivates a store (multi-tenancy §3.1). A suspended store
 * resolves — identification succeeds — but is not served.
 *
 * handle() is the operation's only authorized caller; execute() is its
 * registered handler (§6.5).
 */
class SetTenantStatusAction
{
    public function __construct(private readonly CentralOperations $central) {}

    public function handle(string $id, string $status): Tenant
    {
        if (! in_array($status, [TenantSchema::STATUS_ACTIVE, TenantSchema::STATUS_SUSPENDED], true)) {
            throw new StatusChangeFailedException;
        }

        $result = $this->central->run(new SetTenantStatusOperation((int) $id, $status), static::class);

        return $result instanceof Tenant
            ? $result
            : throw new LogicException('The tenant.set-status operation did not return a tenant.');
    }

    /** Runs inside CentralOperations::run(). */
    public function execute(SetTenantStatusOperation $operation): Tenant
    {
        try {
            $tenant = Tenant::query()->findOrFail($operation->tenantId);

            $tenant->update([TenantSchema::STATUS => $operation->status]);

            return $tenant;
        } catch (Throwable $e) {
            report($e);

            throw new StatusChangeFailedException;
        }
    }
}
