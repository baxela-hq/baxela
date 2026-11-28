<?php

namespace Modules\Auth\Actions\Admin\Role;

use Illuminate\Auth\Access\AuthorizationException;
use Modules\Auth\Models\Role;
use Modules\Auth\Schemas\Role\RoleSchema;
use Modules\Core\Support\Tenancy\TenantContext;

abstract class AbstractRoleAction
{
    public function __construct(protected Role $model) {}

    /**
     * Pull permission_ids out of the validated payload (it is not a column)
     * and return it, or null when absent/null so callers skip the sync.
     *
     * @param  array<string, mixed>  $params
     * @return array<int, int|string>|null
     */
    protected function takePermissionIds(array &$params): ?array
    {
        $permissionIds = $params[RoleSchema::PERMISSION_IDS] ?? null;
        unset($params[RoleSchema::PERMISSION_IDS]);

        if ($permissionIds === null) {
            return null;
        }

        return (array) $permissionIds;
    }

    /**
     * The super-admin role is system-defined: its bypass comes from
     * Gate::before, not attached permissions, so it must never be
     * modified or deleted through the API. The name check also keeps a
     * tenant-scoped role from borrowing the platform bypass's name.
     *
     * @throws AuthorizationException
     */
    protected function assertNotSuperAdmin(Role $role): void
    {
        if ($role->{RoleSchema::NAME} === Role::SUPER_ADMIN) {
            throw new AuthorizationException;
        }
    }

    /**
     * Role CRUD is a Category-2 tenant operation (multi-tenancy §6.5):
     * the active tenant's id is the role's spatie team (§5). Fails closed
     * outside MODE_TENANT — there is no legitimate unscoped role write.
     *
     * @throws AuthorizationException
     */
    protected function requireContextTenantId(): int
    {
        $context = app(TenantContext::class);
        $tenantId = $context->tenantId();

        if ($context->mode() !== TenantContext::MODE_TENANT || $tenantId === null) {
            throw new AuthorizationException;
        }

        return $tenantId;
    }

    /**
     * A role write only ever touches the active tenant's own rows — the
     * platform (NULL-team) roles are unreachable from this path (§5).
     *
     * @throws AuthorizationException
     */
    protected function assertWithinContextTenant(Role $role): void
    {
        if ((int) ($role->{RoleSchema::TENANT_ID} ?? 0) !== $this->requireContextTenantId()) {
            throw new AuthorizationException;
        }
    }
}
