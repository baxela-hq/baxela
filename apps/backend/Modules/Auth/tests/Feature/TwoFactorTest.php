<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\Models\TwoFactorChallenge;
use Modules\Auth\Models\User;
use Modules\Auth\Schemas\TwoFactorChallenge\TwoFactorChallengeSchema;
use Modules\Auth\Schemas\User\UserSchema;
use Modules\Auth\Support\TwoFactor\TwoFactorService;
use Modules\Auth\Tests\Feature\HelperTrait;
use PragmaRX\Google2FA\Google2FA;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

function twoFactorUser(array $overrides = []): User
{
    $user = User::factory()->create(array_merge([
        UserSchema::PASSWORD => '12345678',
        UserSchema::IS_ACTIVE => true,
        UserSchema::EMAIL_VERIFIED_AT => now(),
    ], $overrides));

    return $user;
}

function enrolledUser(string $secret = ''): array
{
    $g2fa = app(Google2FA::class);
    $secret = $secret !== '' ? $secret : $g2fa->generateSecretKey(32);
    $codes = app(TwoFactorService::class)->generateRecoveryCodes();

    $user = twoFactorUser([
        UserSchema::TWO_FACTOR_SECRET => $secret,
        UserSchema::TWO_FACTOR_RECOVERY_CODES => app(TwoFactorService::class)->hashRecoveryCodes($codes),
        UserSchema::TWO_FACTOR_CONFIRMED_AT => now(),
    ]);

    return [$user, $secret, $codes, $g2fa];
}

it('sign-in returns a challenge when 2FA is enrolled, no bearer token', function () {
    [$user, $secret] = enrolledUser();

    $response = $this->postJson($this->baseUrl('/public/auth/signin'), [
        UserSchema::EMAIL => $user->{UserSchema::EMAIL},
        UserSchema::PASSWORD => '12345678',
    ])->assertOk();

    expect($response->json('data.two_factor_required'))->toBeTrue()
        ->and($response->json('data.challenge_token'))->not->toBeNull()
        // No bearer token leaked
        ->and($response->json('data.token'))->toBeNull();

    // A challenge row exists
    expect(TwoFactorChallenge::count())->toBe(1);
});

it('non-2FA user signs in unchanged (no challenge fields)', function () {
    $user = twoFactorUser();

    $response = $this->postJson($this->baseUrl('/public/auth/signin'), [
        UserSchema::EMAIL => $user->{UserSchema::EMAIL},
        UserSchema::PASSWORD => '12345678',
    ])->assertOk();

    expect($response->json('data.two_factor_required'))->toBeNull()
        ->and($response->json('data.token'))->not->toBeNull()
        ->and(TwoFactorChallenge::count())->toBe(0);
});

it('verifies a correct TOTP code and receives a bearer token', function () {
    [$user, $secret, , $g2fa] = enrolledUser();

    $signIn = $this->postJson($this->baseUrl('/public/auth/signin'), [
        UserSchema::EMAIL => $user->{UserSchema::EMAIL},
        UserSchema::PASSWORD => '12345678',
    ])->assertOk();

    $challengeToken = $signIn->json('data.challenge_token');

    $response = $this->postJson($this->baseUrl('/public/auth/2fa/verify'), [
        'challenge_token' => $challengeToken,
        'code' => $g2fa->getCurrentOtp($secret),
    ])->assertOk();

    expect($response->json('data.token'))->not->toBeNull();

    // Challenge consumed
    $hash = hash('sha256', $challengeToken);
    $challenge = TwoFactorChallenge::find($hash);
    expect($challenge->isConsumed())->toBeTrue();
});

it('rejects a wrong TOTP code but keeps the challenge alive for retry', function () {
    [$user] = enrolledUser();

    $signIn = $this->postJson($this->baseUrl('/public/auth/signin'), [
        UserSchema::EMAIL => $user->{UserSchema::EMAIL},
        UserSchema::PASSWORD => '12345678',
    ])->assertOk();

    $challengeToken = $signIn->json('data.challenge_token');

    // Wrong code
    $this->postJson($this->baseUrl('/public/auth/2fa/verify'), [
        'challenge_token' => $challengeToken,
        'code' => '000000',
    ])->assertStatus(400);

    // Challenge still active (failed_attempts incremented)
    $hash = hash('sha256', $challengeToken);
    $challenge = TwoFactorChallenge::find($hash);
    expect($challenge->{TwoFactorChallengeSchema::FAILED_ATTEMPTS})->toBe(1)
        ->and($challenge->isConsumed())->toBeFalse();
});

it('invalidates a challenge after 5 failed attempts', function () {
    [$user] = enrolledUser();

    $signIn = $this->postJson($this->baseUrl('/public/auth/signin'), [
        UserSchema::EMAIL => $user->{UserSchema::EMAIL},
        UserSchema::PASSWORD => '12345678',
    ])->assertOk();

    $challengeToken = $signIn->json('data.challenge_token');

    for ($i = 0; $i < 6; $i++) {
        $this->postJson($this->baseUrl('/public/auth/2fa/verify'), [
            'challenge_token' => $challengeToken,
            'code' => '000000',
        ]);
    }

    $hash = hash('sha256', $challengeToken);
    $challenge = TwoFactorChallenge::find($hash);
    expect($challenge->isExhausted())->toBeTrue();
});

it('rejects a replayed TOTP code (same code used twice)', function () {
    [$user, $secret, , $g2fa] = enrolledUser();
    $code = $g2fa->getCurrentOtp($secret);

    $signIn = $this->postJson($this->baseUrl('/public/auth/signin'), [
        UserSchema::EMAIL => $user->{UserSchema::EMAIL},
        UserSchema::PASSWORD => '12345678',
    ])->assertOk();

    $challengeToken = $signIn->json('data.challenge_token');

    // First submission succeeds
    $this->postJson($this->baseUrl('/public/auth/2fa/verify'), [
        'challenge_token' => $challengeToken,
        'code' => $code,
    ])->assertOk();

    // Get a NEW challenge for the same user
    $signIn2 = $this->postJson($this->baseUrl('/public/auth/signin'), [
        UserSchema::EMAIL => $user->{UserSchema::EMAIL},
        UserSchema::PASSWORD => '12345678',
    ])->assertOk();

    $challengeToken2 = $signIn2->json('data.challenge_token');

    // Replay the same code → rejected by the replay guard (D8)
    $this->postJson($this->baseUrl('/public/auth/2fa/verify'), [
        'challenge_token' => $challengeToken2,
        'code' => $code,
    ])->assertStatus(400);
});

it('consumes a recovery code and issues a token', function () {
    [$user, , $codes] = enrolledUser();

    $signIn = $this->postJson($this->baseUrl('/public/auth/signin'), [
        UserSchema::EMAIL => $user->{UserSchema::EMAIL},
        UserSchema::PASSWORD => '12345678',
    ])->assertOk();

    $challengeToken = $signIn->json('data.challenge_token');

    $response = $this->postJson($this->baseUrl('/public/auth/2fa/verify'), [
        'challenge_token' => $challengeToken,
        'code' => $codes[0], // First recovery code
    ])->assertOk();

    expect($response->json('data.token'))->not->toBeNull();

    // Recovery code consumed (7 remaining)
    $user->refresh();
    expect(count($user->{UserSchema::TWO_FACTOR_RECOVERY_CODES}))->toBe(7);
});

it('sign-in challenge expires after 5 minutes', function () {
    [$user] = enrolledUser();

    $signIn = $this->postJson($this->baseUrl('/public/auth/signin'), [
        UserSchema::EMAIL => $user->{UserSchema::EMAIL},
        UserSchema::PASSWORD => '12345678',
    ])->assertOk();

    $challengeToken = $signIn->json('data.challenge_token');
    $hash = hash('sha256', $challengeToken);

    // Expire the challenge
    TwoFactorChallenge::find($hash)->update([
        TwoFactorChallengeSchema::EXPIRES_AT => now()->subSecond(),
    ]);

    $this->postJson($this->baseUrl('/public/auth/2fa/verify'), [
        'challenge_token' => $challengeToken,
        'code' => '123456',
    ])->assertStatus(400);
});

it('rejects an unknown challenge token', function () {
    enrolledUser();

    $this->postJson($this->baseUrl('/public/auth/2fa/verify'), [
        'challenge_token' => str_repeat('a', 64),
        'code' => '123456',
    ])->assertStatus(400);
});

it('a super-admin without 2FA gets forced enrollment, not a dashboard token', function () {
    config(['auth.two_factor.force_super_admin' => true]);

    $user = twoFactorUser();
    $user->assignRole($this->superAdminRole());

    $response = $this->postJson($this->baseUrl('/public/auth/signin'), [
        UserSchema::EMAIL => $user->{UserSchema::EMAIL},
        UserSchema::PASSWORD => '12345678',
    ])->assertOk();

    expect($response->json('data.two_factor_enrollment_required'))->toBeTrue()
        ->and($response->json('data.token'))->toBeNull()
        ->and($response->json('data.challenge_token'))->not->toBeNull();
});

it('forced enrollment is off when the config says so', function () {
    config(['auth.two_factor.force_super_admin' => false]);

    $user = twoFactorUser();
    $user->assignRole($this->superAdminRole());

    $response = $this->postJson($this->baseUrl('/public/auth/signin'), [
        UserSchema::EMAIL => $user->{UserSchema::EMAIL},
        UserSchema::PASSWORD => '12345678',
    ])->assertOk();

    expect($response->json('data.token'))->not->toBeNull()
        ->and($response->json('data.two_factor_enrollment_required'))->toBeNull();
});

// --- Self-service management endpoints ---

it('enables 2FA and returns a QR code with no-store', function () {
    $user = twoFactorUser();

    $response = $this->actingAs($user)
        ->postJson($this->baseUrl('/user/2fa/enable'))
        ->assertOk();

    expect($response->json('data.secret'))->not->toBeNull()
        ->and($response->json('data.qr_code_uri'))->not->toBeNull()
        ->and($response->headers->get('Cache-Control'))->toContain('no-store');
});

it('confirms 2FA and returns recovery codes once', function () {
    $user = twoFactorUser();
    $g2fa = app(Google2FA::class);

    $enableResponse = $this->actingAs($user)
        ->postJson($this->baseUrl('/user/2fa/enable'))
        ->assertOk();

    $secret = $enableResponse->json('data.secret');

    $response = $this->actingAs($user)
        ->postJson($this->baseUrl('/user/2fa/confirm'), [
            'code' => $g2fa->getCurrentOtp($secret),
        ])
        ->assertOk();

    expect(count($response->json('data.recovery_codes')))->toBe(8);

    // confirmed_at is set
    expect($user->refresh()->{UserSchema::TWO_FACTOR_CONFIRMED_AT})->not->toBeNull();
});

it('rejects confirmation with a wrong code', function () {
    $user = twoFactorUser();

    $this->actingAs($user)
        ->postJson($this->baseUrl('/user/2fa/enable'))
        ->assertOk();

    $this->actingAs($user)
        ->postJson($this->baseUrl('/user/2fa/confirm'), ['code' => '000000'])
        ->assertStatus(400);

    expect($user->refresh()->{UserSchema::TWO_FACTOR_CONFIRMED_AT})->toBeNull();
});

it('disables 2FA with a valid recovery code', function () {
    [$user, , $codes] = enrolledUser();

    $this->actingAs($user)
        ->postJson($this->baseUrl('/user/2fa/disable'), ['code' => $codes[0]])
        ->assertOk();

    expect($user->refresh()->{UserSchema::TWO_FACTOR_CONFIRMED_AT})->toBeNull()
        ->and($user->{UserSchema::TWO_FACTOR_SECRET})->toBeNull()
        ->and($user->{UserSchema::TWO_FACTOR_RECOVERY_CODES})->toBeNull();
});

it('refuses to disable 2FA with just the password (no code)', function () {
    [$user] = enrolledUser();

    $this->actingAs($user)
        ->postJson($this->baseUrl('/user/2fa/disable'), ['code' => ''])
        ->assertStatus(422);
});

it('refuses to disable 2FA with a wrong code', function () {
    [$user] = enrolledUser();

    $this->actingAs($user)
        ->postJson($this->baseUrl('/user/2fa/disable'), ['code' => '000000'])
        ->assertStatus(400);

    expect($user->refresh()->{UserSchema::TWO_FACTOR_CONFIRMED_AT})->not->toBeNull();
});

it('secret and recovery codes never appear in serialized user resources', function () {
    [$user] = enrolledUser();

    $response = $this->actingAs($user)
        ->getJson($this->baseUrl('/user/account/me'))
        ->assertOk();

    $json = $response->json();
    expect(json_encode($json))->not->toContain('two_factor_secret')
        ->and(json_encode($json))->not->toContain('two_factor_recovery_codes')
        ->and(json_encode($json))->not->toContain('two_factor_last_used_step');
});
