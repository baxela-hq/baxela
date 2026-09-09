<?php

namespace Modules\Auth\Actions\Admin\Role;

use Illuminate\Auth\Access\AuthorizationException;
use Modules\Auth\Models\Role;
use Modules\Auth\Schemas\Role\RoleSchema;

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
     * modified or deleted through the API.
     *
     * @throws AuthorizationException
     */
    protected function assertNotSuperAdmin(Role $role): void
    {
        if ($role->{RoleSchema::NAME} === Role::SUPER_ADMIN) {
            throw new AuthorizationException;
        }
    }
}
