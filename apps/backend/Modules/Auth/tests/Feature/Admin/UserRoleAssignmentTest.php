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
    $user->assignRole($role);

    return $user;
}

function targetUser(): User
{
    return User::factory()->create([
        UserSchema::IS_ACTIVE => true,
    ]);
}

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

    expect($superAdminUser->fresh()->roles->pluck('name')->all())->toBe(['super-admin']);
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
