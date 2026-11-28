<?php

namespace Modules\Auth\Gateways;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Auth\Models\Permission;
use Modules\Auth\Models\Role;
use Modules\Auth\Models\User;
use Modules\Auth\Schemas\GuardsEnum;
use Modules\Auth\Schemas\Role\RoleSchema;
use Modules\Auth\Schemas\User\UserSchema;
use Modules\Auth\Support\PlatformRoleAssignment;
use Modules\Core\Contracts\Gateways\Auth\AccessGatewayInterface;
use Modules\Core\Support\Tenancy\TenantContext;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

class AccessGateway implements AccessGatewayInterface
{
    public function userCan(Authenticatable $user, string $permission): bool
    {
        try {
            return $user->can($permission);
        } catch (PermissionDoesNotExist) {
            // an unsynced admin route must fail closed, not blow up
            return false;
        }
    }

    public function isPlatformSuperAdmin(Authenticatable $user): bool
    {
        if (! $user instanceof User) {
            return false;
        }

        return PlatformRoleAssignment::holdsSuperAdmin($user);
    }

    public function adminUserIds(): array
    {
        // Team-agnostic pivot existence (multi-tenancy §5): with spatie
        // teams enabled the `roles()` relation filters by the active team
        // and is no longer a safe all-team query. The morph-disciplined
        // pivot query answers across every team (§10 row 103 discipline).
        return User::query()
            ->where(UserSchema::IS_ACTIVE, true)
            ->whereIntegerInRaw(UserSchema::ID, PlatformRoleAssignment::assignedUserIds())
            ->pluck(UserSchema::ID)
            ->map(fn (int|string $id): int => (int) $id)
            ->all();
    }

    public function tenantAdminUserIds(int $tenantId): array
    {
        // The spatie `roles()` relation is team-filtered through
        // TenantTeamResolver (§5). Running the query as the store makes
        // both the pivot predicate and the role's tenant agree; a user
        // with a role in another store never enters this list.
        return app(TenantContext::class)->runForTenant($tenantId, fn () => User::query()
            ->where(UserSchema::IS_ACTIVE, true)
            ->whereHas('roles', fn ($query) => $query
                // spatie's roles() relation already scopes the pivot and the
                // role row to the active team/tenant; an extra unqualified
                // predicate here would be ambiguous against the pivot's
                // tenant_id column.
                ->where(RoleSchema::TABLE.'.'.RoleSchema::GUARD_NAME, GuardsEnum::WEB->value))
            ->pluck(UserSchema::ID)
            ->map(static fn (int|string $id): int => (int) $id)
            ->all());
    }

    public function getUserEmailsByIds(array $ids): array
    {
        return User::query()
            ->whereIn(UserSchema::ID, $ids)
            ->pluck(UserSchema::EMAIL, UserSchema::ID)
            ->all();
    }

    public function getUserIdsByEmails(array $emails): array
    {
        return User::query()
            ->whereIn(UserSchema::EMAIL, $emails)
            ->pluck(UserSchema::ID, UserSchema::EMAIL)
            ->all();
    }

    public function listUserIds(): array
    {
        return User::query()
            ->pluck(UserSchema::ID)
            ->map(fn (int|string $id): int => (int) $id)
            ->all();
    }

    public function ensureUserByEmail(string $email): int
    {
        $canonical = mb_strtolower(trim($email));

        // Case-insensitive lookup: accounts may predate canonicalization,
        // and identity must win over storage casing.
        $existing = User::query()
            ->whereRaw('LOWER('.UserSchema::EMAIL.') = ?', [$canonical])
            ->first();
        if ($existing !== null) {
            return (int) $existing->getKey();
        }

        try {
            $record = User::query()->create([
                UserSchema::EMAIL => $canonical,
                // Inert until activation/verification (C6): the sign-in
                // token path re-checks is_active under a row lock, so an
                // imported account cannot authenticate, and the random
                // password is unguessable — nobody can pass a credential
                // check on it.
                UserSchema::IS_ACTIVE => false,
                UserSchema::PASSWORD => Str::password(32),
            ]);
        } catch (UniqueConstraintViolationException) {
            // A concurrent import created the same address — adopt it.
            $record = User::query()
                ->where(UserSchema::EMAIL, $canonical)
                ->firstOrFail();
        }

        return (int) $record->getKey();
    }

    public function lockUser(int $userId): bool
    {
        return User::query()
            ->whereKey($userId)
            ->lockForUpdate()
            ->first([UserSchema::ID]) !== null;
    }

    /**
     * The per-module admin wildcards a store owner needs. One wildcard per
     * module, never a global `*`: platform routes carry no permission row
     * (§4.1), but a wildcard MATCHES the checked name — a global wildcard
     * would silently unlock the platform. The membership wildcard is
     * Category 2 (the store's own member administration); the Tenant
     * module's platform CRUD (tenants.*) is deliberately absent.
     */
    private const array TENANT_OWNER_WILDCARDS = [
        'audit.admin.*',
        'auth.admin.*',
        'cart.admin.*',
        'catalog.admin.*',
        'contact.admin.*',
        'content.admin.*',
        'discount.admin.*',
        'inventory.admin.*',
        'media.admin.*',
        'menu.admin.*',
        'notification.admin.*',
        'order.admin.*',
        'payment.admin.*',
        'setting.admin.*',
        'shipping.admin.*',
        'support.admin.*',
        'tax.admin.*',
        'tenant.admin.memberships.*',
        'user.admin.*',
        'webhook.admin.*',
    ];

    public function seedTenantRoles(int $tenantId): int
    {
        // Wildcards are rows too (SyncPermissionsAction's note): created
        // here where they are granted, idempotently.
        $ids = [];
        foreach (self::TENANT_OWNER_WILDCARDS as $name) {
            $ids[] = (int) Permission::query()->firstOrCreate([
                'name' => $name,
                'guard_name' => GuardsEnum::WEB->value,
            ])->getKey();
        }

        $role = Role::query()->firstOrCreate([
            'name' => Role::TENANT_OWNER,
            'guard_name' => GuardsEnum::WEB->value,
            'tenant_id' => $tenantId,
        ]);

        $role->syncPermissions($ids);

        return (int) $role->getKey();
    }

    public function assignTenantRole(int $userId, int $tenantId, int $roleId): void
    {
        $role = Role::query()->findOrFail($roleId);

        if ((int) ($role->{RoleSchema::TENANT_ID} ?? 0) !== $tenantId) {
            // Platform and foreign-team roles are not assignable here (§5).
            throw new InvalidArgumentException('The role does not belong to the tenant.');
        }

        // The pivot team follows the context (TenantTeamResolver, §5):
        // the attach runs as the tenant the role belongs to, restoring
        // the caller's context afterward (§6.5 B7).
        app(TenantContext::class)
            ->runForTenant($tenantId, function () use ($userId, $role): void {
                User::query()->findOrFail($userId)->assignRole($role);
            });
    }

    public function syncTenantMemberRoles(int $userId, int $tenantId, array $roleIds): void
    {
        $roles = Role::query()->findOrFail($roleIds);

        foreach ($roles as $role) {
            if ((int) ($role->{RoleSchema::TENANT_ID} ?? 0) !== $tenantId) {
                throw new InvalidArgumentException('The role does not belong to the tenant.');
            }
        }

        // Team-scoped sync as the owning store (§5): only that store's
        // pivot rows are replaced — the member's roles in other stores are
        // untouched.
        app(TenantContext::class)
            ->runForTenant($tenantId, function () use ($userId, $roles): void {
                User::query()->findOrFail($userId)->syncRoles($roles);
            });
    }
}
