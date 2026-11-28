<?php

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Auth\Models\Role;
use Modules\Auth\Models\User;
use Modules\Auth\Schemas\GuardsEnum;
use Modules\Auth\Support\PlatformRoleAssignment;
use Modules\Tenant\Models\Tenant;
use Modules\Tenant\Schemas\Tenant\TenantSchema;
use Modules\Tenant\Tests\Feature\HelperTrait as TenantHelpers;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);
uses(TenantHelpers::class);

/**
 * Spatie teams keyed by tenant_id (multi-tenancy §5, §10 rows 69, 103):
 * the platform (NULL-team) role is unique at the database level, identical
 * role names coexist across stores, the bypass is morph-disciplined, and
 * role CRUD is scoped to the active store.
 */

/** The base URL of the Auth module's admin API. */
function authAdminUrl(string $path = ''): string
{
    return 'api/v1/auth/admin'.$path;
}

it('rejects a duplicate NULL-team platform role at the database level (row 69)', function () {
    Role::query()->firstOrCreate([
        'name' => 'platform-role',
        'guard_name' => GuardsEnum::WEB->value,
    ]);

    $rejected = false;

    try {
        // Runs in its own savepoint: PostgreSQL aborts the surrounding
        // RefreshDatabase transaction on a constraint error.
        DB::transaction(fn () => DB::table('auth_roles')->insert([
            'name' => 'platform-role',
            'guard_name' => GuardsEnum::WEB->value,
            'tenant_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]));
    } catch (QueryException) {
        $rejected = true;
    }

    expect($rejected)->toBeTrue()
        ->and(Role::query()->whereNull('tenant_id')->where('name', 'platform-role')->count())->toBe(1);
});

it('lets identical role names coexist across stores (§5)', function () {
    $acme = $this->createTenant('acme');
    $beta = $this->createTenant('beta');

    $id = fn (Tenant $tenant): int => (int) $tenant->getKey();

    // Same name in two stores — the [tenant_id, name, guard_name] unique
    // keeps them apart.
    Role::query()->create(['name' => 'manager', 'guard_name' => GuardsEnum::WEB->value, 'tenant_id' => $id($acme)]);
    Role::query()->create(['name' => 'manager', 'guard_name' => GuardsEnum::WEB->value, 'tenant_id' => $id($beta)]);

    expect(DB::table('auth_roles')->where('name', 'manager')->count())->toBe(2);

    // The same store cannot hold the name twice.
    $rejected = false;
    try {
        DB::transaction(fn () => Role::query()->create([
            'name' => 'manager',
            'guard_name' => GuardsEnum::WEB->value,
            'tenant_id' => $id($acme),
        ]));
    } catch (QueryException) {
        $rejected = true;
    }

    expect($rejected)->toBeTrue();
});

it('scopes role CRUD to the active store over HTTP (§5)', function () {
    $acme = $this->createTenant('acme');
    $beta = $this->createTenant('beta');

    $this->actingAs($this->superAdminUser(), 'sanctum');
    $acmeHeader = ['X-Tenant-Id' => (string) $acme->getKey()];
    $betaHeader = ['X-Tenant-Id' => (string) $beta->getKey()];

    // Create lands in the named store's team, not in any other.
    $created = $this->postJson(authAdminUrl('/roles'), ['name' => 'clerk'], $acmeHeader)
        ->assertStatus(201)
        ->assertJsonPath('data.name', 'clerk');

    $clerkId = (string) $created->json('data.id');

    expect(DB::table('auth_roles')->where('name', 'clerk')->value('tenant_id'))
        ->toBe((int) $acme->getKey());

    // The listing answers only the active store.
    $this->getJson(authAdminUrl('/roles'), $acmeHeader)
        ->assertOk()
        ->assertJsonCount(1, 'data');

    // The other store's identical name is a separate row: creating it
    // there succeeds instead of colliding.
    $this->postJson(authAdminUrl('/roles'), ['name' => 'clerk'], $betaHeader)
        ->assertStatus(201);

    // Beta's clerk is unreachable for deletion from Acme's context.
    $betaClerkId = (string) Role::query()
        ->where('name', 'clerk')
        ->where('tenant_id', $beta->getKey())
        ->value('id');

    $this->deleteJson(authAdminUrl('/roles/'.$betaClerkId), [], $acmeHeader)
        ->assertStatus(403);

    // Acme's own clerk deletes fine.
    $this->deleteJson(authAdminUrl('/roles/'.$clerkId), [], $acmeHeader)
        ->assertNoContent();
});

it('keeps the super-admin bypass morph-disciplined (row 103)', function () {
    $admin = User::factory()->superAdmin()->create();

    $tenant = Tenant::query()->create([
        TenantSchema::NAME => 'Collision',
        TenantSchema::SLUG => 'collision',
        TenantSchema::EMAIL => 'collision@example.test',
        TenantSchema::STATUS => TenantSchema::STATUS_ACTIVE,
    ]);

    $superAdminRole = Role::query()->where('name', Role::SUPER_ADMIN)->firstOrFail();

    // A planted pivot: a NON-user model row whose id collides with the
    // admin's. The bypass must never answer through it.
    DB::table('auth_model_has_roles')->insert([
        'tenant_id' => $tenant->getKey(),
        'role_id' => $superAdminRole->getKey(),
        'model_id' => $admin->getKey(),
        'model_type' => $tenant->getMorphClass(),
    ]);

    expect(PlatformRoleAssignment::holdsSuperAdmin($admin))->toBeTrue()
        ->and($admin->can('any.permission'))->toBeTrue();

    // The planted row grants nobody else anything either.
    $other = User::factory()->active()->create();
    expect(PlatformRoleAssignment::holdsSuperAdmin($other))->toBeFalse();
});

it('answers the bypass whatever the active permission team is (§5)', function () {
    $admin = User::factory()->superAdmin()->create();

    $tenant = $this->createTenant('team-shift');

    $registrar = app(PermissionRegistrar::class);
    $registrar->setPermissionsTeamId((int) $tenant->getKey());

    expect(PlatformRoleAssignment::holdsSuperAdmin($admin))->toBeTrue();

    $registrar->setPermissionsTeamId(null);
});
