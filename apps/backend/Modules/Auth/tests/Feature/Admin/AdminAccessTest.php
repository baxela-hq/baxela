<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\Models\User;
use Modules\Auth\Schemas\User\UserSchema;
use Modules\Auth\Tests\Feature\HelperTrait;
use Tests\TestCase;

uses(TestCase::class);
uses(RefreshDatabase::class);
uses(HelperTrait::class);

it('allows a super-admin on any admin route even with no synced permissions', function () {
    $this->actingAs($this->superAdminUser());

    $this->getJson('api/v1/auth/admin/roles')->assertOk();
    $this->getJson('api/v1/core/admin/countries')->assertOk();
});

it('denies an authenticated user without roles — fail closed, never 500', function () {
    $user = User::factory()->create([
        UserSchema::IS_ACTIVE => true,
    ]);
    $this->actingAs($user);

    $this->getJson('api/v1/auth/admin/roles')
        ->assertStatus(403)
        ->assertJsonPath('code', 'http.403');
});

it('denies a guest with 401', function () {
    $this->getJson('api/v1/auth/admin/roles')->assertStatus(401);
});
