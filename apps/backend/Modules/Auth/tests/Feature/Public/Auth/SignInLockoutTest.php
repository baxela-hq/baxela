<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Modules\Auth\Models\SignInLockout;
use Modules\Auth\Models\TwoFactorChallenge;
use Modules\Auth\Models\User;
use Modules\Auth\Schemas\SignInLockout\SignInLockoutSchema;
use Modules\Auth\Schemas\User\UserSchema;
use Modules\Auth\Support\SignInLockout\FailureOutcome;
use Modules\Auth\Support\SignInLockout\SignInLockoutService;
use Modules\Auth\Support\TwoFactor\TwoFactorService;
use Modules\Auth\Tests\Feature\HelperTrait;
use Modules\Core\Contracts\Events\Auth\NewDeviceSignInEvent;
use Modules\Core\Contracts\Events\Auth\UserSignedInEvent;
use PragmaRX\Google2FA\Google2FA;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

const S4_SIGN_IN = '/public/auth/signin';

function s4User(string $password = 'Passw0rd!'): User
{
    return User::factory()->create([
        UserSchema::PASSWORD => $password,
        UserSchema::IS_ACTIVE => true,
    ]);
}

/**
 * @return array{0: User, 1: string, 2: Google2FA}
 */
function s4EnrolledUser(): array
{
    $g2fa = app(Google2FA::class);
    $secret = $g2fa->generateSecretKey(32);
    $codes = app(TwoFactorService::class)->generateRecoveryCodes();

    $user = s4User('12345678');
    $user->fill([
        UserSchema::TWO_FACTOR_SECRET => $secret,
        UserSchema::TWO_FACTOR_RECOVERY_CODES => app(TwoFactorService::class)->hashRecoveryCodes($codes),
        UserSchema::TWO_FACTOR_CONFIRMED_AT => now(),
    ]);
    $user->save();

    return [$user, $secret, $g2fa];
}

function s4FailedSignIn(User $user, string $password = 'wrong-password'): TestResponse
{
    return test()->postJson(test()->baseUrl(S4_SIGN_IN), [
        UserSchema::EMAIL => $user->{UserSchema::EMAIL},
        UserSchema::PASSWORD => $password,
    ]);
}

function s4Row(string $email): SignInLockout
{
    return SignInLockout::query()
        ->where(SignInLockoutSchema::EMAIL_HASH, SignInLockoutService::emailKey($email))
        ->firstOrFail();
}

function s4LockedConfig(): void
{
    config([
        'auth.lockout.max_attempts' => 2,
        'auth.lockout.lockout_minutes' => 15,
    ]);
}

// --- Threshold + envelope ---

it('locks the account after max_attempts failed sign-ins and rejects even the correct password', function () {
    s4LockedConfig();
    $user = s4User();

    s4FailedSignIn($user);
    s4FailedSignIn($user);

    $row = s4Row($user->email);
    expect((int) $row->{SignInLockoutSchema::FAILED_ATTEMPTS})->toBe(2)
        ->and($row->{SignInLockoutSchema::LOCKED_AT})->not->toBeNull()
        ->and($row->{SignInLockoutSchema::LOCKED_UNTIL})->not->toBeNull();

    // Third attempt: the CORRECT password — still refused while locked.
    $response = s4FailedSignIn($user, 'Passw0rd!');

    $response->assertStatus(429);
    $response->assertJsonPath('code', 'auth.sign_in.locked');
    expect($response->json('meta.retry_after_minutes'))->toBe(15)
        ->and($response->json('meta.retry_after_seconds'))->toBeGreaterThan(0);
});

it('gives unknown and known email addresses identical lockout envelopes (no disclosure)', function () {
    s4LockedConfig();
    $known = s4User();
    $unknownEmail = 'ghost.'.$known->id.'@example.com';

    foreach ([$known->{UserSchema::EMAIL}, $unknownEmail] as $email) {
        for ($i = 0; $i < 2; $i++) {
            test()->postJson(test()->baseUrl(S4_SIGN_IN), [
                UserSchema::EMAIL => $email,
                UserSchema::PASSWORD => 'wrong-password',
            ]);
        }

        $locked = test()->postJson(test()->baseUrl(S4_SIGN_IN), [
            UserSchema::EMAIL => $email,
            UserSchema::PASSWORD => 'wrong-password',
        ]);

        $locked->assertStatus(429);
        expect($locked->json('code'))->toBe('auth.sign_in.locked')
            ->and($locked->json('meta.retry_after_minutes'))->toBe(15);
    }
});

it('keeps the per-ip throttle and the account lockout independent', function () {
    config(['auth.rate_limit.sign_in' => 2, 'auth.lockout.max_attempts' => 5]);
    $user = s4User();
    $data = [UserSchema::EMAIL => $user->{UserSchema::EMAIL}, UserSchema::PASSWORD => 'wrong-password'];

    test()->postJson(test()->baseUrl(S4_SIGN_IN), $data);
    test()->postJson(test()->baseUrl(S4_SIGN_IN), $data);
    $third = test()->postJson(test()->baseUrl(S4_SIGN_IN), $data);

    // The throttle fires before the action: the throttle envelope, not the
    // lockout envelope. The first two attempts DID reach the counter (two
    // failures); the throttled third never did.
    $third->assertStatus(429);
    $third->assertJsonPath('code', 'http.429');
    expect($third->headers->get('Retry-After'))->not->toBeNull()
        ->and(SignInLockout::query()->count())->toBe(1)
        ->and((int) SignInLockout::query()->first()->{SignInLockoutSchema::FAILED_ATTEMPTS})->toBe(2);
});

// --- Fixed window: no extension, expiry, inactivity reset ---

it('does not extend an active lock when further failures arrive during it', function () {
    s4LockedConfig();
    $user = s4User();

    s4FailedSignIn($user);
    s4FailedSignIn($user);

    $row = s4Row($user->email);
    $lockedUntil = $row->{SignInLockoutSchema::LOCKED_UNTIL};
    $lockedAt = $row->{SignInLockoutSchema::LOCKED_AT};
    $attempts = (int) $row->{SignInLockoutSchema::FAILED_ATTEMPTS};

    s4FailedSignIn($user);
    s4FailedSignIn($user);

    $row->refresh();
    expect($row->{SignInLockoutSchema::LOCKED_UNTIL}->equalTo($lockedUntil))->toBeTrue()
        ->and($row->{SignInLockoutSchema::LOCKED_AT}->equalTo($lockedAt))->toBeTrue()
        ->and((int) $row->{SignInLockoutSchema::FAILED_ATTEMPTS})->toBe($attempts);
});

it('reopens the account after the lock expires', function () {
    s4LockedConfig();
    $user = s4User();

    s4FailedSignIn($user);
    s4FailedSignIn($user);

    $this->travel(16)->minutes();

    $response = s4FailedSignIn($user, 'Passw0rd!')->assertOk();
    expect($response->json('data.token'))->not->toBeNull();
});

it('accumulates failures spaced inside the inactivity window', function () {
    s4LockedConfig();
    $user = s4User();

    s4FailedSignIn($user);
    $this->travel(5)->minutes();
    s4FailedSignIn($user);

    // 5-minute spacing < 15-minute window → second failure crosses the
    // threshold (D2c/D2i: inactivity-reset, not a rolling window).
    expect(s4Row($user->email)->isLocked())->toBeTrue();
});

it('resets the counter after a failure gap beyond the inactivity window', function () {
    s4LockedConfig();
    $user = s4User();

    s4FailedSignIn($user);
    $this->travel(16)->minutes();
    s4FailedSignIn($user);

    $row = s4Row($user->email);
    expect((int) $row->{SignInLockoutSchema::FAILED_ATTEMPTS})->toBe(1)
        ->and($row->{SignInLockoutSchema::LOCKED_UNTIL})->toBeNull();
});

// --- D2f: clear-on-valid-password (incl. the 2FA-pending case) ---

it('clears the failure counter the moment the password verifies', function () {
    s4LockedConfig();
    $user = s4User();

    s4FailedSignIn($user);
    s4FailedSignIn($user, 'Passw0rd!')->assertOk();

    $row = s4Row($user->email);
    expect((int) $row->{SignInLockoutSchema::FAILED_ATTEMPTS})->toBe(0)
        ->and($row->{SignInLockoutSchema::LAST_FAILED_AT})->toBeNull();

    // A single later failure does not lock — earlier failures are gone.
    s4FailedSignIn($user);
    $row->refresh();
    expect((int) $row->{SignInLockoutSchema::FAILED_ATTEMPTS})->toBe(1)
        ->and($row->{SignInLockoutSchema::LOCKED_UNTIL})->toBeNull();
});

it('clears the counter at password verification even when the 2FA challenge stays pending', function () {
    s4LockedConfig();
    [$user, $secret, $g2fa] = s4EnrolledUser();

    s4FailedSignIn($user);

    // Correct password → challenge issued; the counter was already cleared.
    $challenge = test()->postJson(test()->baseUrl(S4_SIGN_IN), [
        UserSchema::EMAIL => $user->{UserSchema::EMAIL},
        UserSchema::PASSWORD => '12345678',
    ])->assertOk()->json('data.challenge_token');
    expect($challenge)->not->toBeNull()
        ->and((int) s4Row($user->email)->{SignInLockoutSchema::FAILED_ATTEMPTS})->toBe(0);

    // Abandon the challenge; one later failure must not lock.
    s4FailedSignIn($user);
    $row = s4Row($user->email);
    expect((int) $row->{SignInLockoutSchema::FAILED_ATTEMPTS})->toBe(1)
        ->and($row->{SignInLockoutSchema::LOCKED_UNTIL})->toBeNull();

    // The abandoned challenge remains usable on its own terms.
    $row2 = TwoFactorChallenge::find(hash('sha256', $challenge));
    expect($row2->isConsumed())->toBeFalse()
        ->and($g2fa->getCurrentOtp($secret))->toBeString();
});

// --- D2h (F26): a lock landing mid-flow stops the sign-in flow ---

it('stops the flow with a lockout response when a lock lands after the pre-check', function () {
    s4LockedConfig();
    $user = s4User();

    // Row exists, unlocked, below threshold — the pre-check will pass.
    s4FailedSignIn($user);

    $emailHash = SignInLockoutService::emailKey($user->email);

    // Deterministic mid-request barrier (F26): the moment the pre-check
    // SELECT runs, a "concurrent" request establishes the lock — exactly
    // the cache-lease-expiry interleaving.
    $established = false;
    DB::listen(function ($query) use (&$established, $emailHash): void {
        if ($established || ! str_contains($query->sql, 'auth_sign_in_lockouts')) {
            return;
        }

        if (str_starts_with(strtolower(ltrim($query->sql)), 'select')) {
            $established = true;
            SignInLockout::query()
                ->where(SignInLockoutSchema::EMAIL_HASH, $emailHash)
                ->update([
                    SignInLockoutSchema::FAILED_ATTEMPTS => 2,
                    SignInLockoutSchema::LOCKED_AT => now(),
                    SignInLockoutSchema::LOCKED_UNTIL => now()->addMinutes(15),
                ]);
        }
    });

    // Correct password: pre-check passes, attempt succeeds, clearAttempts
    // discovers the active lock → 429, no token.
    $response = s4FailedSignIn($user, 'Passw0rd!');

    $response->assertStatus(429);
    $response->assertJsonPath('code', 'auth.sign_in.locked');
    expect(DB::table('personal_access_tokens')->where('tokenable_id', $user->id)->count())->toBe(0);

    // The guard is a no-op under an active lock — nothing mutated.
    $service = app(SignInLockoutService::class);
    expect($service->clearAttempts($emailHash))->toBe(FailureOutcome::LockedWhileInFlight);
    $row = s4Row($user->email);
    expect((int) $row->{SignInLockoutSchema::FAILED_ATTEMPTS})->toBe(2)
        ->and($row->{SignInLockoutSchema::LOCKED_UNTIL})->not->toBeNull();
});

it('does not issue a 2FA challenge for an account that locked mid-flight', function () {
    s4LockedConfig();
    [$user, $secret, $g2fa] = s4EnrolledUser();

    s4FailedSignIn($user);

    $emailHash = SignInLockoutService::emailKey($user->email);
    $established = false;
    DB::listen(function ($query) use (&$established, $emailHash): void {
        if ($established || ! str_contains($query->sql, 'auth_sign_in_lockouts')) {
            return;
        }

        if (str_starts_with(strtolower(ltrim($query->sql)), 'select')) {
            $established = true;
            SignInLockout::query()
                ->where(SignInLockoutSchema::EMAIL_HASH, $emailHash)
                ->update([
                    SignInLockoutSchema::FAILED_ATTEMPTS => 2,
                    SignInLockoutSchema::LOCKED_AT => now(),
                    SignInLockoutSchema::LOCKED_UNTIL => now()->addMinutes(15),
                ]);
        }
    });

    $response = test()->postJson(test()->baseUrl(S4_SIGN_IN), [
        UserSchema::EMAIL => $user->{UserSchema::EMAIL},
        UserSchema::PASSWORD => '12345678',
    ]);

    // No challenge, no token — the flow stops at the lockout envelope.
    $response->assertStatus(429);
    $response->assertJsonPath('code', 'auth.sign_in.locked');
    expect($response->json('data.two_factor_required'))->toBeNull()
        ->and(TwoFactorChallenge::query()->count())->toBe(0)
        ->and($g2fa->getCurrentOtp($secret))->toBeString();
});

// --- F1: the token-issuance boundary refuses locked accounts on all paths ---

it('rejects a valid sign-in 2FA challenge when the account became locked', function () {
    s4LockedConfig();
    Event::fake();
    [$user, $secret, $g2fa] = s4EnrolledUser();

    $challengeToken = test()->postJson(test()->baseUrl(S4_SIGN_IN), [
        UserSchema::EMAIL => $user->{UserSchema::EMAIL},
        UserSchema::PASSWORD => '12345678',
    ])->assertOk()->json('data.challenge_token');
    expect($challengeToken)->not->toBeNull();

    // Two wrong-password attempts after the challenge was issued → locked.
    s4FailedSignIn($user);
    s4FailedSignIn($user);

    $response = test()->postJson(test()->baseUrl('/public/auth/2fa/verify'), [
        'challenge_token' => $challengeToken,
        'code' => $g2fa->getCurrentOtp($secret),
    ]);

    $response->assertStatus(429);
    $response->assertJsonPath('code', 'auth.sign_in.locked');

    // The challenge was NOT consumed — retryable after the lock expires.
    $challengeRow = TwoFactorChallenge::find(hash('sha256', $challengeToken));
    expect($challengeRow)->not->toBeNull()
        ->and($challengeRow->isConsumed())->toBeFalse()
        ->and(DB::table('personal_access_tokens')->where('tokenable_id', $user->id)->count())->toBe(0);

    Event::assertNotDispatched(UserSignedInEvent::class);
    Event::assertNotDispatched(NewDeviceSignInEvent::class);
});

it('rejects a valid forced-enrollment challenge when the account became locked', function () {
    s4LockedConfig();
    config(['auth.two_factor.force_super_admin' => true]);
    Event::fake();

    $user = s4User('12345678');
    $user->assignRole($this->superAdminRole());
    $g2fa = app(Google2FA::class);

    $signIn = test()->postJson(test()->baseUrl(S4_SIGN_IN), [
        UserSchema::EMAIL => $user->{UserSchema::EMAIL},
        UserSchema::PASSWORD => '12345678',
    ])->assertOk();

    $challengeToken = $signIn->json('data.challenge_token');
    expect($signIn->json('data.two_factor_enrollment_required'))->toBeTrue();

    $secretResponse = test()->postJson(test()->baseUrl('/public/auth/2fa/enrollment/secret'), [
        'challenge_token' => $challengeToken,
    ])->assertOk();

    s4FailedSignIn($user);
    s4FailedSignIn($user);

    $response = test()->postJson(test()->baseUrl('/public/auth/2fa/enrollment/confirm'), [
        'challenge_token' => $challengeToken,
        'code' => $g2fa->getCurrentOtp($secretResponse->json('data.secret')),
    ]);

    $response->assertStatus(429);
    $response->assertJsonPath('code', 'auth.sign_in.locked');

    $challengeRow = TwoFactorChallenge::find(hash('sha256', $challengeToken));
    expect($challengeRow->isConsumed())->toBeFalse()
        ->and(DB::table('personal_access_tokens')->where('tokenable_id', $user->id)->count())->toBe(0);

    Event::assertNotDispatched(UserSignedInEvent::class);
});

// --- Service-level semantics ---

it('refuses to extend an active lock when a failure lands after the threshold (F23)', function () {
    s4LockedConfig();
    $service = app(SignInLockoutService::class);
    $emailHash = SignInLockoutService::emailKey('racer@example.com');

    expect($service->recordFailure($emailHash))->toBe(FailureOutcome::Recorded);
    expect($service->recordFailure($emailHash))->toBe(FailureOutcome::Recorded);

    $row = s4Row('racer@example.com');
    $until = $row->{SignInLockoutSchema::LOCKED_UNTIL};
    $at = $row->{SignInLockoutSchema::LOCKED_AT};
    $last = $row->{SignInLockoutSchema::LAST_FAILED_AT};
    $attempts = (int) $row->{SignInLockoutSchema::FAILED_ATTEMPTS};

    expect($service->recordFailure($emailHash))->toBe(FailureOutcome::LockedWhileInFlight);

    $row->refresh();
    expect($row->{SignInLockoutSchema::LOCKED_UNTIL}->equalTo($until))->toBeTrue()
        ->and($row->{SignInLockoutSchema::LOCKED_AT}->equalTo($at))->toBeTrue()
        ->and($row->{SignInLockoutSchema::LAST_FAILED_AT}->equalTo($last))->toBeTrue()
        ->and((int) $row->{SignInLockoutSchema::FAILED_ATTEMPTS})->toBe($attempts);
});

it('resets the counter when the last failure is older than the inactivity window (D2i)', function () {
    s4LockedConfig();
    $service = app(SignInLockoutService::class);
    $emailHash = SignInLockoutService::emailKey('stale@example.com');

    expect($service->recordFailure($emailHash))->toBe(FailureOutcome::Recorded);
    $this->travel(16)->minutes();
    expect($service->recordFailure($emailHash))->toBe(FailureOutcome::Recorded);

    $row = s4Row('stale@example.com');
    expect((int) $row->{SignInLockoutSchema::FAILED_ATTEMPTS})->toBe(1)
        ->and($row->{SignInLockoutSchema::LOCKED_UNTIL})->toBeNull();
});

it('bootstraps a lockout row once per email (F19, sequential)', function () {
    $service = app(SignInLockoutService::class);
    $emailHash = SignInLockoutService::emailKey('bootstrap@example.com');

    expect(SignInLockout::query()->count())->toBe(0);

    $service->recordFailure($emailHash);
    $service->recordFailure($emailHash);

    expect(SignInLockout::query()->count())->toBe(1);
});

it('creates the zero row at the boundary when a known user signs in without prior failures', function () {
    $user = s4User();

    s4FailedSignIn($user, 'Passw0rd!')->assertOk();

    $row = s4Row($user->email);
    expect((int) $row->{SignInLockoutSchema::FAILED_ATTEMPTS})->toBe(0)
        ->and($row->{SignInLockoutSchema::LAST_FAILED_AT})->toBeNull();
});

// --- F25: concurrent first-row bootstrap (MySQL row locks) ---

// --- Fail-closed contention ---

it('fails closed with 503 when the per-email gate cannot be acquired', function () {
    $user = s4User();
    $emailHash = SignInLockoutService::emailKey($user->email);

    $held = Cache::lock('sign-in-gate:'.$emailHash, 10);
    $held->get();

    try {
        $response = s4FailedSignIn($user, 'Passw0rd!');

        $response->assertStatus(503);
        $response->assertJsonPath('code', 'auth.sign_in.busy');
    } finally {
        $held->release();
    }
});

// --- F16: password rehash under the gate ---

it('rehashes an outdated password hash during the gated attempt without breaking the flow', function () {
    s4LockedConfig();
    $user = s4User('Passw0rd!');

    // Bcrypt's minimum cost is 4 (= test-configured BCRYPT_ROUNDS), and the
    // `hashed` cast refuses hashes whose cost exceeds the configured one —
    // so the outdated (cost-5) hash is written below Eloquent, at the DB
    // level. needsRehash fires on the cost mismatch; the hasher option
    // key is `rounds` (BcryptHasher::cost()).
    DB::table(UserSchema::TABLE)
        ->where('id', $user->id)
        ->update([UserSchema::PASSWORD => Hash::make('Passw0rd!', ['rounds' => 5])]);
    $user->refresh();

    expect(Hash::needsRehash($user->getRawOriginal(UserSchema::PASSWORD)))->toBeTrue();

    $response = s4FailedSignIn($user, 'Passw0rd!')->assertOk();
    expect($response->json('data.token'))->not->toBeNull()
        ->and(Hash::needsRehash($user->fresh()->getRawOriginal(UserSchema::PASSWORD)))->toBeFalse();
});

// --- Prune ---

it('prunes inert lockout rows and keeps live ones', function () {
    $live = 'live@example.com';
    $expired = 'expired@example.com';
    $zeroRow = 'bootstrap@example.com';

    SignInLockout::query()->create([
        SignInLockoutSchema::EMAIL_HASH => SignInLockoutService::emailKey($live),
        SignInLockoutSchema::FAILED_ATTEMPTS => 2,
        SignInLockoutSchema::LAST_FAILED_AT => now(),
        SignInLockoutSchema::LOCKED_AT => now(),
        SignInLockoutSchema::LOCKED_UNTIL => now()->addMinutes(15),
    ]);

    SignInLockout::query()->create([
        SignInLockoutSchema::EMAIL_HASH => SignInLockoutService::emailKey($expired),
        SignInLockoutSchema::FAILED_ATTEMPTS => 5,
        SignInLockoutSchema::LAST_FAILED_AT => now()->subDays(10),
        SignInLockoutSchema::LOCKED_AT => now()->subDays(10),
        SignInLockoutSchema::LOCKED_UNTIL => now()->subMinutes(30),
    ]);

    SignInLockout::query()->create([
        SignInLockoutSchema::EMAIL_HASH => SignInLockoutService::emailKey($zeroRow),
        SignInLockoutSchema::FAILED_ATTEMPTS => 0,
    ]);

    Artisan::call('auth:prune-sign-in-lockouts');

    expect(SignInLockout::query()->where(SignInLockoutSchema::EMAIL_HASH, SignInLockoutService::emailKey($live))->exists())->toBeTrue()
        ->and(SignInLockout::query()->where(SignInLockoutSchema::EMAIL_HASH, SignInLockoutService::emailKey($expired))->exists())->toBeFalse()
        ->and(SignInLockout::query()->where(SignInLockoutSchema::EMAIL_HASH, SignInLockoutService::emailKey($zeroRow))->exists())->toBeFalse();
});
