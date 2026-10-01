<?php

use Illuminate\Auth\AuthManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Modules\Auth\Models\OtpCode;
use Modules\Auth\Models\User;
use Modules\Auth\Schemas\Otp\OtpCodeActionEnum;
use Modules\Auth\Schemas\Otp\OtpCodeSchema;
use Modules\Auth\Schemas\Otp\OtpCodeTypeEnum;
use Modules\Auth\Schemas\User\UserSchema;
use Modules\Auth\Tests\Feature\HelperTrait;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

$endpoint = '/public/auth/reset-password/verify';
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
        OtpCodeSchema::ACTION => OtpCodeActionEnum::FORGOT_PASSWORD,
        OtpCodeSchema::CODE => $code,
        OtpCodeSchema::EXPIRES_AT => now()->addMinutes(5), // OTP valid for 5 minutes
    ]);
});

it($endpoint.' returns 200 with valid data', function () use ($endpoint, $email, $code) {

    $password = '12345678';
    $data = [
        OtpCodeSchema::CODE => $code,
        OtpCodeSchema::EMAIL => $email,
        UserSchema::PASSWORD => $password,
        UserSchema::PASSWORD.'_confirmation' => $password,
    ];
    $response = $this->postJson($this->baseUrl($endpoint), $data);

    $response->assertStatus(200);
});

it($endpoint.' returns 422 with invalid data', function () use ($endpoint, $email) {

    $data = [
        OtpCodeSchema::CODE => '123123',
        OtpCodeSchema::EMAIL => $email,
    ];
    $response = $this->postJson($this->baseUrl($endpoint), $data);

    $response->assertStatus(422);
});

it($endpoint.' revokes every existing token after a successful reset', function () use ($endpoint, $email, $code) {
    $user = User::query()->where(UserSchema::EMAIL, $email)->firstOrFail();
    $stolenToken = $user->createToken('laptop', ['*'], now()->addDays(30))->plainTextToken;

    $password = 'NewPassword123!';
    $this->postJson($this->baseUrl($endpoint), [
        OtpCodeSchema::CODE => $code,
        OtpCodeSchema::EMAIL => $email,
        UserSchema::PASSWORD => $password,
        UserSchema::PASSWORD.'_confirmation' => $password,
    ])->assertStatus(200);

    // Guard instances are cached across requests in a single test container;
    // forget them so the bearer token is re-resolved (see SessionTest).
    app(AuthManager::class)->forgetGuards();

    $this->getJson($this->baseUrl('/user/account/me'), ['Authorization' => "Bearer {$stolenToken}"])
        ->assertUnauthorized();
});

it('stores OTP codes hashed at rest', function () use ($email, $code) {
    $rawCode = DB::table(OtpCodeSchema::TABLE)
        ->where(OtpCodeSchema::EMAIL, $email)
        ->value(OtpCodeSchema::CODE);

    expect($rawCode)->not->toBe($code)
        ->and(Hash::check($code, $rawCode))->toBeTrue();
});

it('counts failed verification attempts per code', function () use ($endpoint, $email, $code) {
    $password = 'NewPassword123!';
    $this->postJson($this->baseUrl($endpoint), [
        OtpCodeSchema::CODE => '000000',
        OtpCodeSchema::EMAIL => $email,
        UserSchema::PASSWORD => $password,
        UserSchema::PASSWORD.'_confirmation' => $password,
    ]);

    $attempts = (int) OtpCode::query()
        ->where(OtpCodeSchema::EMAIL, $email)
        ->latest()
        ->value(OtpCodeSchema::ATTEMPTS);

    expect($attempts)->toBe(1);

    // The real code still works while under the attempt budget.
    $this->postJson($this->baseUrl($endpoint), [
        OtpCodeSchema::CODE => $code,
        OtpCodeSchema::EMAIL => $email,
        UserSchema::PASSWORD => $password,
        UserSchema::PASSWORD.'_confirmation' => $password,
    ])->assertStatus(200);
});

it('invalidates an OTP after too many failed attempts', function () use ($email) {
    $otp = OtpCode::query()->where(OtpCodeSchema::EMAIL, $email)->latest()->firstOrFail();

    foreach (range(1, OtpCodeSchema::MAX_ATTEMPTS) as $attempt) {
        $otp->recordFailedAttempt();
    }

    expect($otp->isValid())->toBeFalse();
});
