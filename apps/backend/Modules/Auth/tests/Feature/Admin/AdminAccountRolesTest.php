<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\Models\Role;
use Modules\Auth\Models\User;
use Modules\Auth\Schemas\User\UserSchema;
use Modules\Auth\Tests\Feature\HelperTrait;
use Tests\TestCase;

uses(TestCase::class);
uses(RefreshDatabase::class);
uses(HelperTrait::class);

it('lists roles as reference data', function () {
    $this->superAdminRole();
    $this->actingAs($this->superAdminUser());

    $response = $this->getJson($this->baseUrl('/admin/roles'));

    $response->assertOk()->assertJsonStructure([
        'data' => [
            ['id', 'name'],
        ],
    ]);
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
