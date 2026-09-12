<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Modules\Auth\Models\Role;
use Modules\Auth\Models\User;
use Modules\Auth\Schemas\User\UserSchema;
use Modules\Auth\Tests\Feature\HelperTrait;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

it('creates an active user with roles and verifies their email', function () {
    $this->actingAs($this->superAdminUser());

    $role = Role::query()->create([
        'name' => 'content-editor',
        'guard_name' => 'web',
    ]);

    $response = $this->postJson($this->baseUrl('/admin/users'), [
        'email' => 'new-user@example.com',
        'password' => 'secret-password',
        'is_active' => true,
        'comment' => null,
        'role_ids' => [$role->id],
    ])->assertCreated();

    $user = User::query()->find($response->json('data.id'));

    expect($user)->not->toBeNull()
        ->and($user->email)->toBe('new-user@example.com')
        ->and(Hash::check('secret-password', $user->password))->toBeTrue()
        ->and($user->{UserSchema::IS_ACTIVE})->toBeTrue()
        // active users are considered verified at creation
        ->and($user->{UserSchema::EMAIL_VERIFIED_AT})->not->toBeNull()
        ->and($user->roles->pluck('id')->all())->toContain($role->id);
});

it('rejects a duplicate email', function () {
    $this->actingAs($this->superAdminUser());
    $existing = User::factory()->create();

    $this->postJson($this->baseUrl('/admin/users'), [
        'email' => $existing->email,
        'password' => 'secret-password',
        'is_active' => true,
        'comment' => null,
        'role_ids' => null,
    ])->assertStatus(422)->assertJsonPath('code', 'http.422');
});

it('shows a user with their roles', function () {
    $this->actingAs($this->superAdminUser());
    $user = $this->superAdminUser();

    $this->getJson($this->baseUrl('/admin/users/'.$user->id))
        ->assertOk()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.roles.0.name', Role::SUPER_ADMIN);
});

it('deletes a user', function () {
    $this->actingAs($this->superAdminUser());
    $user = User::factory()->create();

    $this->deleteJson($this->baseUrl('/admin/users/'.$user->id))
        ->assertNoContent();

    expect(User::query()->find($user->id))->toBeNull();
});
