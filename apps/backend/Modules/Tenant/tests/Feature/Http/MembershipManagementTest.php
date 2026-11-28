<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\Models\User;
use Modules\Core\Contracts\Gateways\Auth\AccessGatewayInterface;
use Modules\Tenant\Models\Tenant;
use Modules\Tenant\Models\TenantMembership;
use Modules\Tenant\Schemas\TenantMembership\TenantMembershipSchema;
use Modules\Tenant\Tests\Feature\HelperTrait;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

/**
 * Member administration of the ACTIVE store (multi-tenancy §6.5 Category
 * 2, §10 rows 72/104/33): MODE_TENANT writes, permission-guarded through
 * the seeded owner role, structurally confined to one store. Every request
 * names the store explicitly (X-Tenant-Id) — never the implicit default.
 */

/** Seeds the store's default roles and gives the member the owner role. */
function grantOwnerRole(User $member, Tenant $tenant): void
{
    $access = app(AccessGatewayInterface::class);
    $ownerRoleId = $access->seedTenantRoles((int) $tenant->getKey());
    $access->assignTenantRole((int) $member->getKey(), (int) $tenant->getKey(), $ownerRoleId);
}

it('lets a store owner list, invite, activate, suspend, and remove members of their store', function () {
    $tenant = $this->createTenant('acme');
    $owner = User::factory()->active()->create();
    $this->addMembership($owner, $tenant, owner: true);
    grantOwnerRole($owner, $tenant);

    $store = ['X-Tenant-Id' => (string) $tenant->getKey()];
    $this->actingAs($owner, 'sanctum');

    $invited = $this->postJson($this->baseUrl('/admin/memberships'), ['email' => 'staff@example.test'], $store)
        ->assertStatus(201)
        ->assertJsonPath('data.status', 'invited')
        ->assertJsonPath('data.email', 'staff@example.test');

    $membershipId = (string) $invited->json('data.id');

    $this->getJson($this->baseUrl('/admin/memberships'), $store)
        ->assertOk()
        ->assertJsonCount(2, 'data');

    $this->postJson($this->baseUrl('/admin/memberships/'.$membershipId.'/activate'), [], $store)
        ->assertOk()
        ->assertJsonPath('data.status', 'active');

    $this->postJson($this->baseUrl('/admin/memberships/'.$membershipId.'/suspend'), [], $store)
        ->assertOk()
        ->assertJsonPath('data.status', 'suspended');

    $this->deleteJson($this->baseUrl('/admin/memberships/'.$membershipId), [], $store)
        ->assertNoContent();

    expect(TenantMembership::query()->count())->toBe(1);
});

it('rejects a membership write for another store and lists only the active store (row 72, row 104)', function () {
    $mine = $this->createTenant('mine');
    $other = $this->createTenant('other');

    $owner = User::factory()->active()->create();
    $this->addMembership($owner, $mine, owner: true);
    grantOwnerRole($owner, $mine);

    $outsider = User::factory()->active()->create();
    $this->addMembership($outsider, $other, owner: true);

    $store = ['X-Tenant-Id' => (string) $mine->getKey()];
    $this->actingAs($owner, 'sanctum');

    // Row 104: the listing is scoped to the active store — the other
    // store's member is invisible here.
    $this->getJson($this->baseUrl('/admin/memberships'), $store)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.user_id', (int) $owner->getKey());

    // Row 72: every write against another store's membership is refused —
    // the row belongs to a different context tenant and the scoped lookup
    // cannot even find it.
    $otherMembershipId = (string) TenantMembership::query()
        ->where(TenantMembershipSchema::TENANT_ID, $other->getKey())
        ->value(TenantMembershipSchema::ID);

    $this->postJson($this->baseUrl('/admin/memberships/'.$otherMembershipId.'/suspend'), [], $store)
        ->assertStatus(404);
    $this->deleteJson($this->baseUrl('/admin/memberships/'.$otherMembershipId), [], $store)
        ->assertStatus(404);
});

it('cuts a revoked member off on their very next request (row 33)', function () {
    $tenant = $this->createTenant('acme');

    $member = User::factory()->active()->create();
    $this->addMembership($member, $tenant);
    grantOwnerRole($member, $tenant);

    $store = ['X-Tenant-Id' => (string) $tenant->getKey()];
    $this->actingAs($member, 'sanctum');

    // Authorized while the membership holds.
    $this->getJson($this->baseUrl('/admin/memberships'), $store)->assertOk();

    // Revoke: no cache in the authorization path — the next request
    // re-reads the membership.
    TenantMembership::query()
        ->where(TenantMembershipSchema::TENANT_ID, $tenant->getKey())
        ->where(TenantMembershipSchema::USER_ID, $member->getKey())
        ->delete();

    $this->getJson($this->baseUrl('/admin/memberships'), $store)
        ->assertStatus(403);
});

it('refuses to remove or suspend the last active owner, and transfers ownership atomically', function () {
    $tenant = $this->createTenant('acme');
    $owner = User::factory()->active()->create();
    $membership = $this->addMembership($owner, $tenant, owner: true);
    grantOwnerRole($owner, $tenant);

    $store = ['X-Tenant-Id' => (string) $tenant->getKey()];
    $this->actingAs($owner, 'sanctum');

    $this->deleteJson($this->baseUrl('/admin/memberships/'.$membership->getKey()), [], $store)
        ->assertStatus(409);
    $this->postJson($this->baseUrl('/admin/memberships/'.$membership->getKey().'/suspend'), [], $store)
        ->assertStatus(409);

    // Transfer grants and revokes in one transaction: exactly one owner
    // remains, and it is the target.
    $staff = User::factory()->active()->create();
    $staffMembership = $this->addMembership($staff, $tenant);

    $this->postJson($this->baseUrl('/admin/memberships/'.$staffMembership->getKey().'/transfer-ownership'), [], $store)
        ->assertOk()
        ->assertJsonPath('data.is_owner', true);

    $ownerIds = TenantMembership::query()
        ->where(TenantMembershipSchema::TENANT_ID, $tenant->getKey())
        ->where(TenantMembershipSchema::IS_OWNER, true)
        ->where(TenantMembershipSchema::STATUS, TenantMembershipSchema::STATUS_ACTIVE)
        ->pluck(TenantMembershipSchema::USER_ID);

    expect($ownerIds)->toHaveCount(1)
        ->and((int) $ownerIds[0])->toBe((int) $staff->getKey());
});

it('resyncs a member\'s roles within the store without touching their other stores', function () {
    $tenant = $this->createTenant('acme');
    $elsewhere = $this->createTenant('beta');

    $member = User::factory()->active()->create();
    $this->addMembership($member, $tenant);
    $this->addMembership($member, $elsewhere);

    $access = app(AccessGatewayInterface::class);
    $ownerRoleId = $access->seedTenantRoles((int) $tenant->getKey());
    $otherOwnerRoleId = $access->seedTenantRoles((int) $elsewhere->getKey());

    // The member owns the other store; that must survive the sync here.
    $access->assignTenantRole((int) $member->getKey(), (int) $elsewhere->getKey(), $otherOwnerRoleId);

    $owner = User::factory()->active()->create();
    $this->addMembership($owner, $tenant, owner: true);
    grantOwnerRole($owner, $tenant);

    $membershipId = (string) TenantMembership::query()
        ->where(TenantMembershipSchema::TENANT_ID, $tenant->getKey())
        ->where(TenantMembershipSchema::USER_ID, $member->getKey())
        ->value(TenantMembershipSchema::ID);

    $store = ['X-Tenant-Id' => (string) $tenant->getKey()];
    $this->actingAs($owner, 'sanctum');

    $this->putJson($this->baseUrl('/admin/memberships/'.$membershipId.'/roles'), ['role_ids' => [$ownerRoleId]], $store)
        ->assertNoContent();

    // The store's own team grants the role here…
    app(PermissionRegistrar::class)->setPermissionsTeamId((int) $tenant->getKey());
    expect($member->fresh()->hasRole('owner'))->toBeTrue();

    // …and the other store's assignment is untouched.
    app(PermissionRegistrar::class)->setPermissionsTeamId((int) $elsewhere->getKey());
    expect($member->fresh()->hasRole('owner'))->toBeTrue();

    app(PermissionRegistrar::class)->setPermissionsTeamId(null);
});

it('refuses an actor who is not the store\'s owner to transfer ownership', function () {
    $tenant = $this->createTenant('acme');

    $manager = User::factory()->active()->create();
    $this->addMembership($manager, $tenant);
    grantOwnerRole($manager, $tenant);

    $staff = User::factory()->active()->create();
    $staffMembership = $this->addMembership($staff, $tenant);

    $store = ['X-Tenant-Id' => (string) $tenant->getKey()];
    $this->actingAs($manager, 'sanctum');

    $this->postJson($this->baseUrl('/admin/memberships/'.$staffMembership->getKey().'/transfer-ownership'), [], $store)
        ->assertStatus(403);
});
