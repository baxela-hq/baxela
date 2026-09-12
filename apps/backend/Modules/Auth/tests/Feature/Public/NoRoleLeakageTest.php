<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\Models\User;
use Modules\Auth\Schemas\User\UserSchema;
use Modules\Auth\Tests\Feature\HelperTrait;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

it('exposes no roles or is_admin in the sign-in response', function () {
    $email = fake()->email();
    $user = User::factory()->create([
        UserSchema::EMAIL => $email,
        UserSchema::PASSWORD => '12345678',
        UserSchema::IS_ACTIVE => true,
    ]);
    $user->assignRole($this->superAdminRole());

    $response = $this->postJson($this->baseUrl('/public/auth/signin'), [
        UserSchema::EMAIL => $email,
        UserSchema::PASSWORD => '12345678',
    ]);

    $response->assertOk();

    $userPayload = collect($response->json('data.user'));
    expect($userPayload->has('roles'))->toBeFalse()
        ->and($userPayload->has('is_admin'))->toBeFalse();
});

it('exposes no roles or is_admin in the account me response', function () {
    $user = User::factory()->create([
        UserSchema::IS_ACTIVE => true,
    ]);
    $user->assignRole($this->superAdminRole());
    $this->actingAs($user);

    $response = $this->getJson($this->baseUrl('/user/account/me'));

    $response->assertOk();

    $payload = collect($response->json('data'));
    expect($payload->has('roles'))->toBeFalse()
        ->and($payload->has('is_admin'))->toBeFalse();
});
