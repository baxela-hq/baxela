<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Modules\Auth\Models\Permission;
use Modules\Auth\Models\Role;
use Modules\Auth\Models\User;
use Modules\Auth\Schemas\GuardsEnum;
use Modules\Auth\Schemas\User\UserSchema;
use Modules\Core\Http\Middleware\PermissionMiddleware;
use Tests\TestCase;

uses(TestCase::class);
uses(RefreshDatabase::class);

beforeEach(function () {
    Route::middleware(PermissionMiddleware::class)->group(function () {
        Route::get('api/v1/test/admin/unnamed', fn () => 'ok');
        Route::get('api/v1/test/admin/malformed', fn () => 'ok')->name('api.test.products.list');
        Route::get('api/v1/test/admin/named', fn () => 'ok')->name('api.test.admin.things.list');
    });

    $this->superAdmin = User::factory()->create([
        UserSchema::IS_ACTIVE => true,
    ]);
    $this->superAdmin->assignRole(Role::query()->firstOrCreate([
        'name' => Role::SUPER_ADMIN,
        'guard_name' => GuardsEnum::WEB->value,
    ]));
});

it('denies an unnamed admin route even for a super-admin', function () {
    $this->actingAs($this->superAdmin);

    $this->getJson('api/v1/test/admin/unnamed')->assertStatus(403);
});

it('denies a route name without the admin segment even for a super-admin', function () {
    $this->actingAs($this->superAdmin);

    $this->getJson('api/v1/test/admin/malformed')->assertStatus(403);
});

it('allows a properly named admin route when the permission is granted', function () {
    $permission = Permission::query()->firstOrCreate([
        'name' => 'test.admin.things.list',
        'guard_name' => GuardsEnum::WEB->value,
    ]);

    $role = Role::query()->create([
        'name' => 'thing-viewer',
        'guard_name' => GuardsEnum::WEB->value,
    ]);
    $role->givePermissionTo($permission);

    $user = User::factory()->create([
        UserSchema::IS_ACTIVE => true,
    ]);
    $user->assignRole($role);
    $this->actingAs($user);

    $this->getJson('api/v1/test/admin/named')->assertOk();
});
