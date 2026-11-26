<?php

namespace Modules\Auth\Actions;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Modules\Auth\Models\Permission;
use Modules\Auth\Schemas\GuardsEnum;

/**
 * Creates a permission row for every admin route name
 * (`api.{module}.admin.{resource}.{action}` → `{module}.admin.{resource}.{action}`).
 *
 * Wildcard patterns (`catalog.admin.*`) are deliberately NOT created here:
 * a wildcard must exist as an explicit permission record before it can be
 * assigned to a role, so wildcards are created where they are granted
 * (seeders, tests, role management).
 */
class SyncPermissionsAction
{
    /**
     * @return int number of permissions created by this run
     */
    public function __invoke(): int
    {
        $created = 0;

        foreach (Route::getRoutes() as $route) {
            $name = $route->getName();

            if ($name === null || ! str_starts_with($name, 'api.') || ! str_contains($name, '.admin.')) {
                continue;
            }

            // Routes reserved for the platform super-admin get no permission
            // row at all: what has no row can never be assigned to a role,
            // so no store role can escalate to platform administration.
            if ($this->isSuperAdminOnly($name)) {
                continue;
            }

            $permission = Permission::query()->firstOrCreate([
                'name' => Str::after($name, 'api.'),
                'guard_name' => GuardsEnum::WEB->value,
            ]);

            if ($permission->wasRecentlyCreated) {
                $created++;
            }
        }

        return $created;
    }

    private function isSuperAdminOnly(string $name): bool
    {
        foreach ((array) config('core.tenancy.super_admin_only_routes', []) as $prefix) {
            if (is_string($prefix) && $prefix !== '' && str_starts_with($name, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
