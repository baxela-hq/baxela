<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\Models\User;
use Modules\Auth\Schemas\Otp\OtpCodeSchema;
use Modules\Auth\Schemas\User\UserSchema;
use Modules\Auth\Tests\Feature\HelperTrait;
use Tests\TestCase;

uses(TestCase::class);
uses(RefreshDatabase::class);
uses(HelperTrait::class);

$endpoint = '/public/auth/account-activation/request';
$email = fake()->email();

beforeEach(function () use ($email) {
    User::factory([
        UserSchema::EMAIL => $email,
        UserSchema::EMAIL_VERIFIED_AT => null,
    ])->create();
});

it($endpoint.' returns 200 with valid data', function () use ($endpoint, $email) {
    $data = [
        OtpCodeSchema::EMAIL => $email,
    ];
    $response = $this->postJson($this->baseUrl($endpoint), $data);

    $response->assertStatus(200);
});

it($endpoint.' returns 422 with invalid email', function () use ($endpoint) {
    $data = [
        OtpCodeSchema::EMAIL => 'invalid_email',
    ];
    $response = $this->postJson($this->baseUrl($endpoint), $data);

    $response->assertStatus(422);
});
