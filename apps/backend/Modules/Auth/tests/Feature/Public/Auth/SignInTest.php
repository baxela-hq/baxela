<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\Models\User;
use Modules\Auth\Schemas\User\UserSchema;
use Modules\Auth\Tests\Feature\HelperTrait;
use Tests\TestCase;

uses(TestCase::class);
uses(RefreshDatabase::class);
uses(HelperTrait::class);

$endpoint = '/public/auth/signin';
$email = fake()->email();
$password = '12345678';

beforeEach(function () use ($email, $password) {
    User::factory([
        UserSchema::EMAIL => $email,
        UserSchema::PASSWORD => $password,
        UserSchema::IS_ACTIVE => true,
    ])->create();
});

it($endpoint.' returns 200 with valid data', function () use ($endpoint, $email, $password) {
    $data = [
        UserSchema::EMAIL => $email,
        UserSchema::PASSWORD => $password,
    ];
    $response = $this->postJson($this->baseUrl($endpoint), $data);

    $response->assertStatus(200);
});

it($endpoint.' returns 422 with invalid email', function () use ($endpoint) {
    $data = [
        UserSchema::EMAIL => 'invalid_email',
        UserSchema::PASSWORD => 'fake_password',
    ];
    $response = $this->postJson($this->baseUrl($endpoint), $data);

    $response->assertStatus(422);
});
