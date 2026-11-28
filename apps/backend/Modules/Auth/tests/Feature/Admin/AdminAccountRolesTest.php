<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\Models\Role;
use Modules\Auth\Models\User;
use Modules\Auth\Schemas\User\UserSchema;
use Modules\Auth\Tests\Feature\HelperTrait;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

it('lists roles as reference data', function () {
    $this->superAdminRole();
    $this->actingAs($this->superAdminUser());

    // The listing is scoped to the active store (§5): the platform
    // NULL-team rows are never listed, so the store's own roles are
    // seeded for the assertion.
    app(\Modules\Core\Contracts\Gateways\Auth\AccessGatewayInterface::class)->seedTenantRoles(1);

    $response = $this->getJson($this->baseUrl('/admin/roles'));

    $response->assertOk()->assertJsonStructure([
        'data' => [
            ['id', 'name'],
        ],
    ]);

    $response->assertJsonPath('data.0.name', 'owner');
});

it('shows the acting admin account with roles', function () {
    $this->actingAs($this->superAdminUser());

    $response = $this->getJson($this->baseUrl('/admin/account'));

    $response->assertOk();
    expect($response->json('data.email'))->not->toBeEmpty();
    expect($response->json('data.roles.0.name'))->toBe(Role::SUPER_ADMIN);
});

it('denies the account endpoint to a role-less authenticated user', function () {
    $user = User::factory()->create([
        UserSchema::IS_ACTIVE => true,
    ]);
    $this->actingAs($user);

    $this->getJson($this->baseUrl('/admin/account'))->assertStatus(403);
});
