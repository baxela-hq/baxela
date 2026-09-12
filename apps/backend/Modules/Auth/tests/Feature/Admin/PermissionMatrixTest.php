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
    $user->assignRole($role);

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
