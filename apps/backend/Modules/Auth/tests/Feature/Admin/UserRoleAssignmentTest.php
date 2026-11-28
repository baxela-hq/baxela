<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\Models\Role;
use Modules\Auth\Models\User;
use Modules\Auth\Schemas\GuardsEnum;
use Modules\Auth\Schemas\User\UserSchema;
use Modules\Auth\Tests\Feature\HelperTrait;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

beforeEach(function () {
    $this->artisan('auth:sync-permissions');
});

function userManagerUser(): User
{
    $role = Role::query()->firstOrCreate([
        'name' => 'user-manager',
        'guard_name' => GuardsEnum::WEB->value,
    ]);
    $role->syncPermissions([
        'auth.admin.users.list',
        'auth.admin.users.show',
        'auth.admin.users.create',
        'auth.admin.users.update',
    ]);

    $user = User::factory()->create([
        UserSchema::IS_ACTIVE => true,
    ]);
    // §3.2: a store admin is a member — the authorization boundary
    // demands an active membership in the resolved (default) store. The
    // default tenant row is seeded first: memberships FK the tenants
    // table (runtime mirrors tests/TestCase::withDefaultStoreMembership).
    $tenantId = (int) \Modules\Tenant\Models\Tenant::query()->firstOrCreate(
        ['slug' => \Modules\Tenant\Database\Seeders\TenantDatabaseSeeder::DEFAULT_SLUG],
        ['name' => 'Default Store', 'email' => 'store@localhost', 'status' => 'active'],
    )->getKey();

    // The permission team follows the context (§5): the role assignment
    // and the membership run as the default store, and the context stays
    // there for the rest of the test so every pivot and every resolved
    // request agree.
    app(\Modules\Core\Support\Tenancy\TenantContext::class)->runForTenant($tenantId, function () use ($user, $role): void {
        $user->assignRole($role);
        \Modules\Tenant\Models\TenantMembership::query()->create([
            'user_id' => $user->getKey(),
            'status' => 'active',
            'is_owner' => true,
            'joined_at' => now(),
        ]);
    });
    app(\Modules\Core\Support\Tenancy\TenantContext::class)->forTenant($tenantId);


    return $user;
}

function targetUser(): User
{
    return User::factory()->create([
        UserSchema::IS_ACTIVE => true,
    ]);
}

/**
 * @param  array<string, mixed>  $payload
 */
function patchUser(User $target, array $payload)
{
    return test()->patchJson('api/v1/auth/admin/users/'.$target->id, $payload);
}

it('keeps roles unchanged when role_ids is omitted', function () {
    $target = targetUser();
    $target->assignRole(userManagerUser()->roles->first());

    $this->actingAs(userManagerUser());

    patchUser($target, [
        UserSchema::EMAIL => $target->email,
        UserSchema::IS_ACTIVE => true,
    ])->assertOk();

    expect($target->fresh()->roles->pluck('name')->all())->toBe(['user-manager']);
});

it('removes all roles when role_ids is an empty array', function () {
    $target = targetUser();
    $target->assignRole($this->superAdminRole());

    $this->actingAs($this->superAdminUser()); // only a super-admin may strip the super-admin role

    patchUser($target, [
        UserSchema::EMAIL => $target->email,
        UserSchema::IS_ACTIVE => true,
        UserSchema::ROLE_IDS => [],
    ])->assertOk();

    expect($target->fresh()->roles)->toBeEmpty();
});

it('replaces roles when role_ids is a non-empty array', function () {
    $viewer = Role::query()->create([
        'name' => 'product-viewer',
        'guard_name' => GuardsEnum::WEB->value,
    ]);

    $target = targetUser();
    $target->assignRole($this->superAdminRole());

    $this->actingAs($this->superAdminUser());

    patchUser($target, [
        UserSchema::EMAIL => $target->email,
        UserSchema::IS_ACTIVE => true,
        UserSchema::ROLE_IDS => [$viewer->id],
    ])->assertOk();

    expect($target->fresh()->roles->pluck('name')->all())->toBe(['product-viewer']);
});

it('denies a non-super-admin granting the super-admin role', function () {
    $target = targetUser();
    $superAdmin = $this->superAdminRole();

    $this->actingAs(userManagerUser());

    patchUser($target, [
        UserSchema::EMAIL => $target->email,
        UserSchema::IS_ACTIVE => true,
        UserSchema::ROLE_IDS => [$superAdmin->id],
    ])->assertStatus(403);

    expect($target->fresh()->roles)->toBeEmpty();
});

it('denies a non-super-admin touching the roles of a super-admin', function () {
    $superAdminUser = $this->superAdminUser();
    $viewer = Role::query()->create([
        'name' => 'product-viewer',
        'guard_name' => GuardsEnum::WEB->value,
    ]);

    $this->actingAs(userManagerUser());

    patchUser($superAdminUser, [
        UserSchema::EMAIL => $superAdminUser->email,
        UserSchema::IS_ACTIVE => true,
        UserSchema::ROLE_IDS => [$viewer->id],
    ])->assertStatus(403);

    // The platform-role read is team-context-immune (§5): the pivot was
    // written under the built-in default team and this test's context is
    // the seeded default row — the team-filtered relation would answer
    // empty for a perfectly intact assignment.
    expect(\Modules\Auth\Support\PlatformRoleAssignment::holdsSuperAdmin($superAdminUser->fresh()))->toBeTrue();
});

it('lets a non-super-admin manage non-super-admin roles', function () {
    $viewer = Role::query()->create([
        'name' => 'product-viewer',
        'guard_name' => GuardsEnum::WEB->value,
    ]);

    $target = targetUser();
    $this->actingAs(userManagerUser());

    patchUser($target, [
        UserSchema::EMAIL => $target->email,
        UserSchema::IS_ACTIVE => true,
        UserSchema::ROLE_IDS => [$viewer->id],
    ])->assertOk();

    expect($target->fresh()->roles->pluck('name')->all())->toBe(['product-viewer']);
});

it('lets a super-admin grant the super-admin role', function () {
    $target = targetUser();
    $superAdmin = $this->superAdminRole();

    $this->actingAs($this->superAdminUser());

    patchUser($target, [
        UserSchema::EMAIL => $target->email,
        UserSchema::IS_ACTIVE => true,
        UserSchema::ROLE_IDS => [$superAdmin->id],
    ])->assertOk();

    expect($target->fresh()->roles->pluck('name')->all())->toBe(['super-admin']);
});
