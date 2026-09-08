<?php

namespace Modules\Auth\Actions\Admin\User;

use Illuminate\Auth\Access\AuthorizationException;
use Modules\Auth\Models\Role;
use Modules\Auth\Models\User;
use Modules\Auth\Schemas\User\UserSchema;
use Modules\Core\Utils\Auth;

abstract class AbstractUserAction
{
    public function __construct(protected User $model) {}

    /**
     * Pull role_ids out of the validated payload (it is not a column) and
     * return it, or null when absent/null so callers skip the sync entirely.
     *
     * @param  array<string, mixed>  $params
     * @return array<int, int|string>|null
     */
    protected function takeRoleIds(array &$params): ?array
    {
        $roleIds = $params[UserSchema::ROLE_IDS] ?? null;
        unset($params[UserSchema::ROLE_IDS]);

        if ($roleIds === null) {
            return null;
        }

        return (array) $roleIds;
    }

    /**
     * Only super-admins may grant or strip the super-admin role; every other
     * role can be synced freely by whoever may manage users.
     *
     * @param  array<int, int|string>  $roleIds
     * @param  User|null  $target  user being updated (null on create)
     *
     * @throws AuthorizationException
     */
    protected function assertRoleAssignmentAllowed(array $roleIds, ?User $target = null): void
    {
        $actingUser = Auth::user();

        if ($actingUser instanceof User && $actingUser->hasRole(Role::SUPER_ADMIN)) {
            return;
        }

        $grantsSuperAdmin = Role::query()
            ->whereIn('id', $roleIds)
            ->pluck('name')
            ->contains(Role::SUPER_ADMIN);

        if ($grantsSuperAdmin || ($target !== null && $target->hasRole(Role::SUPER_ADMIN))) {
            throw new AuthorizationException;
        }
    }
}
