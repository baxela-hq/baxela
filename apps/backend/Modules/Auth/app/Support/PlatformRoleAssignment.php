<?php

namespace Modules\Auth\Support;

use Illuminate\Support\Facades\DB;
use Modules\Auth\Models\Role;
use Modules\Auth\Models\User;
use Modules\Auth\Schemas\GuardsEnum;

use function config;

/**
 * Team-context-immune queries against the role pivot (multi-tenancy §5).
 *
 * With spatie teams enabled, the `roles()` relation filters by the active
 * permission team, so the platform super-admin bypass (a NULL-team role)
 * must read the pivot directly. Every query here constrains `model_type`
 * to the user model's morph type — a numeric-id collision with another
 * model type can never answer (§10 row 103) — and the guard through the
 * joined role row (the pivot itself carries no guard column). The staff
 * guard is the house `web` guard: seeders and role CRUD write roles on it
 * exclusively.
 */
final class PlatformRoleAssignment
{
    private function __construct() {}

    public static function morphType(): string
    {
        return (new User)->getMorphClass();
    }

    public static function holdsSuperAdmin(User $user, ?string $guard = null): bool
    {
        $tables = (array) config('permission.table_names');
        $teamKey = (string) config('permission.column_names.team_foreign_key', 'tenant_id');

        return DB::table(((string) $tables['model_has_roles']).' as pivot')
            ->join(((string) $tables['roles']).' as roles', 'pivot.role_id', '=', 'roles.id')
            ->where('pivot.model_id', $user->getKey())
            ->where('pivot.model_type', self::morphType())
            ->where('roles.name', Role::SUPER_ADMIN)
            ->where('roles.guard_name', $guard ?? GuardsEnum::WEB->value)
            ->whereNull("roles.{$teamKey}")
            ->exists();
    }

    /**
     * Every user id carrying a role assignment in any team or none —
     * team-agnostic, unlike the `roles()` relation (§5, adminUserIds).
     *
     * @return list<int>
     */
    public static function assignedUserIds(): array
    {
        $tables = (array) config('permission.table_names');

        return DB::table((string) $tables['model_has_roles'])
            ->where('model_type', self::morphType())
            ->distinct()
            ->pluck('model_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();
    }
}
