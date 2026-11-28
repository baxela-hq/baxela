<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\Models\Permission;
use Modules\Auth\Models\Role;
use Modules\Auth\Models\User;
use Modules\Auth\Schemas\GuardsEnum;
use Modules\Auth\Schemas\User\UserSchema;

uses(RefreshDatabase::class);

function createPermission(string $name): Permission
{
    return Permission::query()->firstOrCreate([
        'name' => $name,
        'guard_name' => GuardsEnum::WEB->value,
    ]);
}

/**
 * @param  array<string, mixed>  $permissionNames
 */
function createUserWithRole(string $roleName, array $permissionNames): User
{
    $role = Role::query()->firstOrCreate([
        'name' => $roleName,
        'guard_name' => GuardsEnum::WEB->value,
    ]);
    $role->syncPermissions(array_map('createPermission', $permissionNames));

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

it('grants every product action to a wildcard permission record', function () {
    $user = createUserWithRole('catalog-manager', ['catalog.admin.products.*']);
    $this->actingAs($user);

    $this->getJson('api/v1/catalog/admin/products')->assertOk();
    $this->postJson('api/v1/catalog/admin/products', [])->assertStatus(422); // authorized, invalid payload

    $this->getJson('api/v1/order/admin/orders')->assertStatus(403);
});

it('grants only the exact action a concrete permission covers', function () {
    $user = createUserWithRole('product-viewer', ['catalog.admin.products.list']);
    $this->actingAs($user);

    $this->getJson('api/v1/catalog/admin/products')->assertOk();
    $this->postJson('api/v1/catalog/admin/products', [])->assertStatus(403);
});

it('fails closed when the route permission has not been synced', function () {
    $user = createUserWithRole('geo-viewer', ['core.admin.countries.list']);
    $this->actingAs($user);

    // catalog.admin.products.list has no permission record in this test
    $this->getJson('api/v1/catalog/admin/products')
        ->assertStatus(403)
        ->assertJsonPath('code', 'http.403');
});
