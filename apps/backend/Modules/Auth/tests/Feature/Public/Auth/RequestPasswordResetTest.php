<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\Models\OtpCode;
use Modules\Auth\Models\User;
use Modules\Auth\Schemas\Otp\OtpCodeSchema;
use Modules\Auth\Schemas\User\UserSchema;
use Modules\Auth\Tests\Feature\HelperTrait;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

$endpoint = '/public/auth/reset-password/request';
$email = fake()->email();

beforeEach(function () use ($email) {
    User::factory([
        UserSchema::EMAIL => $email,
    ])->create();
});

it($endpoint.' returns 200 with valid data', function () use ($endpoint, $email) {
    $data = [
        OtpCodeSchema::EMAIL => $email,
    ];
    $response = $this->postJson($this->baseUrl($endpoint), $data);

    $response->assertStatus(200);
});

it($endpoint.' returns 422 with invalid data', function () use ($endpoint) {
    $data = [
        OtpCodeSchema::EMAIL => 'invalid_email_format',
    ];
    $response = $this->postJson($this->baseUrl($endpoint), $data);

    $response->assertStatus(422);
});

it($endpoint.' does not reveal whether an unknown email is registered', function () use ($endpoint) {
    $unknown = 'nobody@'.str_replace('.', '-', fake()->unique()->domainWord()).'.test';

    $this->postJson($this->baseUrl($endpoint), [
        OtpCodeSchema::EMAIL => $unknown,
    ])->assertStatus(200);

    // No code may be minted for an address with no account.
    expect(OtpCode::query()->where(OtpCodeSchema::EMAIL, $unknown)->exists())->toBeFalse();
});
