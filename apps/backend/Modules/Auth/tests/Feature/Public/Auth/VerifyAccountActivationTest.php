<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\Models\OtpCode;
use Modules\Auth\Models\User;
use Modules\Auth\Schemas\Otp\OtpCodeActionEnum;
use Modules\Auth\Schemas\Otp\OtpCodeSchema;
use Modules\Auth\Schemas\Otp\OtpCodeTypeEnum;
use Modules\Auth\Schemas\User\UserSchema;
use Modules\Auth\Tests\Feature\HelperTrait;
use Tests\TestCase;

uses(TestCase::class);
uses(RefreshDatabase::class);
uses(HelperTrait::class);

$endpoint = '/public/auth/account-activation/verify';
$email = fake()->email();
$code = '123456';

beforeEach(function () use ($email, $code) {
    User::factory([
        UserSchema::EMAIL => $email,
        UserSchema::EMAIL_VERIFIED_AT => null,
    ])->create();

    OtpCode::query()->create([
        OtpCodeSchema::EMAIL => $email,
        OtpCodeSchema::TYPE => OtpCodeTypeEnum::EMAIL,
        OtpCodeSchema::ACTION => OtpCodeActionEnum::VERIFY_EMAIL,
        OtpCodeSchema::CODE => $code,
        OtpCodeSchema::EXPIRES_AT => now()->addMinutes(5), // OTP valid for 5 minutes
    ]);
});

it($endpoint.' returns 200 with valid data', function () use ($endpoint, $email, $code) {
    $data = [
        OtpCodeSchema::CODE => $code,
        OtpCodeSchema::EMAIL => $email,
    ];
    $response = $this->postJson($this->baseUrl($endpoint), $data);

    $response->assertStatus(200);
});

it($endpoint.' returns 400 with invalid data', function () use ($endpoint, $email) {
    $data = [
        OtpCodeSchema::CODE => '321321',
        OtpCodeSchema::EMAIL => $email,
    ];
    $response = $this->postJson($this->baseUrl($endpoint), $data);

    $response->assertStatus(400);
});
