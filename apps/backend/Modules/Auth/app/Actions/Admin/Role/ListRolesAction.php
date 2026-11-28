<?php

namespace Modules\Auth\Actions\Admin\Role;

use Illuminate\Database\Eloquent\Collection;
use Modules\Auth\Models\Role;
use Modules\Auth\Schemas\Role\RoleSchema;
use Modules\Core\Support\Tenancy\TenantContext;

class ListRolesAction
{
    /**
     * The active tenant's roles only (multi-tenancy §5, §10 row 104
     * discipline): the platform NULL-team rows and other stores' roles
     * are never listed.
     */
    public function handle(): Collection
    {
        $tenantId = app(TenantContext::class)->tenantId();

        if ($tenantId === null) {
            // Fail closed — the middleware has already authorized, but the
            // listing itself never answers unscoped.
            abort(403);
        }

        return Role::query()
            ->where(RoleSchema::TENANT_ID, $tenantId)
            ->with(RoleSchema::PERMISSIONS)
            ->orderBy(RoleSchema::ID)
            ->get();
    }
}
