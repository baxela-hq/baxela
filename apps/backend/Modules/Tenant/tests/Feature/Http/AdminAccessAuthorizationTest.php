<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Modules\Auth\Models\User;
use Modules\Core\Contracts\Gateways\Auth\AccessGatewayInterface;
use Modules\Tenant\Schemas\Tenant\TenantSchema;
use Modules\Tenant\Tests\Feature\HelperTrait;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

/**
 * The authorization boundary (multi-tenancy §3.2, §10 rows 6, 7, 67 and
 * the §3.1 suspended-store rules): the resolved store is earned — active
 * membership or the platform super-admin bypass — per request, with no
 * cache between requests.
 */

/** A real admin route probing the whole stack: identification, sanctum, authorization. */
function storeUrl(): string
{
    return 'api/v1/tenant/admin/memberships';
}

it('refuses a signed-in user entering a store they hold no membership in (row 6)', function () {
    $store = $this->createTenant('acme');
    $storeB = $this->createTenant('beta');

    $outsider = User::factory()->active()->create();
    $this->addMembership($outsider, $storeB);

    $this->actingAs($outsider, 'sanctum');

    $this->getJson(storeUrl(), ['X-Tenant-Id' => (string) $store->getKey()])
        ->assertStatus(403);
});

it('lets the platform super-admin switch stores by header alone (row 7)', function () {
    $store = $this->createTenant('acme');

    $this->actingAs($this->superAdminUser(), 'sanctum');

    $this->getJson(storeUrl(), ['X-Tenant-Id' => (string) $store->getKey()])
        ->assertOk();
});

it('selects the single membership on a central host without a header; asks with several; refuses with none (row 67)', function () {
    $this->productionLikeTenancy();

    $only = $this->createTenant('only-store');
    $member = User::factory()->active()->create();
    $this->addMembership($member, $only);

    $access = app(AccessGatewayInterface::class);
    $ownerRoleId = $access->seedTenantRoles((int) $only->getKey());
    $access->assignTenantRole((int) $member->getKey(), (int) $only->getKey(), $ownerRoleId);

    $this->actingAs($member, 'sanctum');

    // Exactly one membership → that store, without a header. The request
    // host is carried by an absolute URL — the only reliable way to set
    // the Host Symfony actually reads in feature requests.
    $this->getJson('http://api.example.test/'.storeUrl())
        ->assertOk();

    // Several memberships → the caller must name the store.
    $second = $this->createTenant('second-store');
    $this->addMembership($member, $second);

    $response = $this->getJson('http://api.example.test/'.storeUrl());
    expect($response->status())->toBe(422)
        ->and($response->json('meta.memberships'))->toContain('only-store')
        ->and($response->json('meta.memberships'))->toContain('second-store');

    // No membership at all → refused.
    $stranger = User::factory()->active()->create();
    $this->actingAs($stranger, 'sanctum');

    $this->getJson('http://api.example.test/'.storeUrl())
        ->assertStatus(403);
});

it('denies a suspended store to its member but keeps the super-admin inside (§3.1)', function () {
    $store = $this->createTenant('acme', status: TenantSchema::STATUS_SUSPENDED);

    $member = User::factory()->active()->create();
    $this->addMembership($member, $store);
    app(AccessGatewayInterface::class)
        ->seedTenantRoles((int) $store->getKey());

    $this->actingAs($member, 'sanctum');

    // A member of a suspended store receives the 403 — the storefront is
    // closed for staff too.
    $this->getJson(storeUrl(), ['X-Tenant-Id' => (string) $store->getKey()])
        ->assertStatus(403);

    // The platform super-admin retains full admin access, including
    // reactivation — the exemption lives in the authorization layer.
    $this->actingAs($this->superAdminUser(), 'sanctum');

    $this->getJson(storeUrl(), ['X-Tenant-Id' => (string) $store->getKey()])
        ->assertOk();
});

it('keeps the super-admin bypass working whatever the permission team is (§5)', function () {
    $storeA = $this->createTenant('acme');
    $storeB = $this->createTenant('beta');

    app(AccessGatewayInterface::class)
        ->seedTenantRoles((int) $storeA->getKey());

    $admin = $this->superAdminUser();

    // Team context points at another store entirely: the NULL-team role
    // must still answer (Gate::before is team-context-immune).
    app(PermissionRegistrar::class)
        ->setPermissionsTeamId((int) $storeB->getKey());

    expect($admin->can('anything.at.all'))->toBeTrue();

    app(PermissionRegistrar::class)->setPermissionsTeamId(null);

    // And through HTTP, the bypass authorizes the suspended-unrelated
    // store switch (row 7's mechanism, asserted at the gate level here).
    $this->actingAs($admin, 'sanctum');
    $this->getJson(storeUrl(), ['X-Tenant-Id' => (string) $storeA->getKey()])
        ->assertOk();
});
