<?php

namespace Modules\Auth\Support;

use LogicException;
use Modules\Auth\Models\Role;
use Modules\Auth\Models\User;
use Modules\Auth\Schemas\GuardsEnum;
use Modules\Core\Contracts\Gateways\Tenant\TenantGatewayInterface;
use Spatie\Permission\PermissionRegistrar;

use function app;

/**
 * Attaches the platform super-admin role with a permission-team context
 * (multi-tenancy §5). With teams enabled every pivot write carries the
 * active team id — the pivot column is NOT NULL — so the attach runs
 * against the default tenant and the previous team context is restored
 * afterward. The bypass itself never reads the team (§5), so the pivot
 * value is bookkeeping, not authorization.
 */
trait AssignsPlatformRole
{
    protected function attachPlatformRole(User $user, ?Role $role = null): void
    {
        $role ??= Role::query()->firstOrCreate([
            'name' => Role::SUPER_ADMIN,
            'guard_name' => GuardsEnum::WEB->value,
        ]);

        $snapshot = app(TenantGatewayInterface::class)->defaultTenant();

        $defaultTenantId = $snapshot === null
            ? throw new LogicException('No default tenant exists; the platform role pivot cannot be written.')
            : $snapshot->id;

        // The pivot team follows the tenant context (§5): seeding runs
        // INACTIVE, so the attach enters the default store's context and
        // restores whatever preceded it (§6.5 B7's set/restore, expressed
        // through the context).
        app(\Modules\Core\Support\Tenancy\TenantContext::class)->runForTenant($defaultTenantId, function () use ($user, $role): void {
            $user->assignRole($role);
        });
    }
}
