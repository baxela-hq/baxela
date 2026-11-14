<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\Auth\Exceptions\AuthException;
use Modules\Auth\Models\Permission;
use Modules\Auth\Models\Role;
use Modules\Auth\Models\SocialAccount;
use Modules\Auth\Models\User;
use Modules\Auth\Schemas\Otp\OtpCodeActionEnum;
use Modules\Auth\Schemas\SocialAccount\SocialAccountSchema;
use Modules\Auth\Support\Google\GoogleEmailClass;
use Modules\Auth\Support\Google\GoogleIdTokenVerifier;
use Modules\Auth\Support\Google\GoogleIdentity;
use Modules\Auth\Support\Google\SignInNonceService;
use Modules\Auth\Tests\Feature\HelperTrait;
use Modules\Core\Contracts\Events\Auth\NewDeviceSignInEvent;
use Modules\Core\Contracts\Events\Auth\OtpRequestedEvent;
use Modules\Core\Contracts\Events\Auth\UserSignedInEvent;
use Modules\Core\Contracts\Events\Auth\UserSignedUpEvent;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

const GOOGLE_ENDPOINT = '/public/auth/google';
const GOOGLE_NONCE_ENDPOINT = '/public/auth/google/nonce';
$clientId = 'test-client-id';

/**
 * Forced-rollback probe for the transactional tests: when enabled, the
 * first SocialAccount::created throws inside the caller's transaction so
 * the whole create+persist attempt rolls back.
 */
class GoogleSignInRollbackProbe
{
    public static bool $enabled = false;
}

beforeEach(function () use ($clientId) {
    config([
        'auth.google.enabled' => true,
        'auth.google.client_id' => $clientId,
    ]);

    $this->app->instance(GoogleIdTokenVerifier::class, new FakeGoogleVerifier($this->app));
});

function googleIdentity(string $email, bool $verified, GoogleEmailClass $class, ?string $hd = null, string $sub = 'google-sub-1'): GoogleIdentity
{
    return new GoogleIdentity(
        sub: $sub,
        email: $email,
        emailVerified: $verified,
        hd: $hd,
        class: $class,
        name: 'Google User',
        picture: 'https://lh3.googleusercontent.com/a/example',
    );
}

function googleSignIn(string $email, bool $verified, GoogleEmailClass $class, array $overrides = []): \Illuminate\Testing\TestResponse
{
    /** @var FakeGoogleVerifier $fake */
    $fake = app(GoogleIdTokenVerifier::class);
    $fake->identity = googleIdentity($email, $verified, $class, $overrides['hd'] ?? null, $overrides['sub'] ?? 'google-sub-1');
    $fake->throw = $overrides['throw'] ?? null;

    $payload = [
        'credential' => 'fake-credential-0123456789',
        'device_name' => $overrides['device_name'] ?? 'test-device',
        'remember' => $overrides['remember'] ?? true,
    ];

    // A real nonce, issued through the real service, consumed by the fake
    // verifier exactly where the real one would (after "verification").
    $nonce = app(SignInNonceService::class)->issue();
    $fake->nonce = $nonce;

    unset($overrides['hd'], $overrides['sub'], $overrides['throw'], $overrides['device_name'], $overrides['remember']);

    return test()->postJson(test()->baseUrl(GOOGLE_ENDPOINT), $payload, $overrides);
}

it(GOOGLE_ENDPOINT.' signs up a new gmail customer immediately (active + verified)', function () {
    $response = googleSignIn('alice@gmail.com', true, GoogleEmailClass::GMAIL);

    $response->assertOk()
        ->assertJsonPath('data.token', fn ($token) => is_string($token) && $token !== '')
        ->assertJsonPath('data.user.email', 'alice@gmail.com');

    $user = User::query()->where('email', 'alice@gmail.com')->firstOrFail();
    expect($user->isActive())->toBeTrue()
        ->and($user->email_verified_at)->not->toBeNull()
        ->and(SocialAccount::query()->count())->toBe(1)
        ->and(SocialAccount::query()->first()->provider_user_id)->toBe('google-sub-1')
        // No Google profile data persisted (invariant: nothing but the link).
        ->and($user->comment)->toBeNull();
});

it(GOOGLE_ENDPOINT.' signs up a new workspace customer via a matching hd claim', function () {
    $response = googleSignIn('bob@corp.example', true, GoogleEmailClass::WORKSPACE, ['hd' => 'corp.example']);

    $response->assertOk();
    expect(User::query()->where('email', 'bob@corp.example')->firstOrFail()->isActive())->toBeTrue();
});

it(GOOGLE_ENDPOINT.' issues a working token for a new customer', function () {
    $response = googleSignIn('alice@gmail.com', true, GoogleEmailClass::GMAIL);
    $token = $response->json('data.token');

    $this->getJson($this->baseUrl('/user/account/me'), ['Authorization' => "Bearer {$token}"])
        ->assertOk()
        ->assertJsonPath('data.email', 'alice@gmail.com');
});

it(GOOGLE_ENDPOINT.' rejects an unverified gmail identity', function () {
    googleSignIn('alice@gmail.com', false, GoogleEmailClass::GMAIL)
        ->assertForbidden()
        ->assertJsonPath('code', 'auth.google.email_unverified');

    expect(User::query()->count())->toBe(0)->and(SocialAccount::query()->count())->toBe(0);
});

it(GOOGLE_ENDPOINT.' rejects an unverified workspace identity', function () {
    googleSignIn('bob@corp.example', false, GoogleEmailClass::WORKSPACE, ['hd' => 'corp.example'])
        ->assertForbidden()
        ->assertJsonPath('code', 'auth.google.email_unverified');

    expect(User::query()->count())->toBe(0);
});

it(GOOGLE_ENDPOINT.' routes a third-party email to the OTP activation flow regardless of the claim value', function () {
    $response = googleSignIn('carol@external.example', false, GoogleEmailClass::THIRD_PARTY);

    $response->assertOk()
        ->assertJsonPath('data.requires_activation', true);
    expect($response->json('data.token'))->toBeNull();

    $user = User::query()->where('email', 'carol@external.example')->firstOrFail();
    expect($user->isActive())->toBeFalse()
        ->and($user->email_verified_at)->toBeNull()
        // No token, no device before activation (invariant 6).
        ->and($user->tokens()->count())->toBe(0)
        ->and(DB::table('auth_user_devices')->count())->toBe(0)
        ->and(DB::table('auth_otp_codes')->count())->toBe(1);
});

it(GOOGLE_ENDPOINT.' routes a verified third-party email to OTP activation too', function () {
    // Google's claim is not authoritative for third-party mailboxes —
    // the OTP proves ownership, whatever the claim says.
    googleSignIn('carol@external.example', true, GoogleEmailClass::THIRD_PARTY)
        ->assertOk()
        ->assertJsonPath('data.requires_activation', true);

    expect(User::query()->firstOrFail()->email_verified_at)->toBeNull();
});

it(GOOGLE_ENDPOINT.' lets a third-party customer complete activation and then sign in', function () {
    $codes = [];
    Event::listen(OtpRequestedEvent::class, function (OtpRequestedEvent $event) use (&$codes) {
        $codes[] = $event->code;
    });

    googleSignIn('carol@external.example', false, GoogleEmailClass::THIRD_PARTY)
        ->assertOk()
        ->assertJsonPath('data.requires_activation', true);

    expect(count($codes))->toBe(1);

    $this->postJson($this->baseUrl('/public/auth/account-activation/verify'), [
        'email' => 'carol@external.example',
        'code' => $codes[0],
    ])->assertOk();

    $user = User::query()->where('email', 'carol@external.example')->firstOrFail();
    expect($user->isActive())->toBeTrue()->and($user->email_verified_at)->not->toBeNull();

    // Same identity, now active — a normal sign-in with a working token.
    $response = googleSignIn('carol@external.example', false, GoogleEmailClass::THIRD_PARTY);
    $response->assertOk()->assertJsonPath('data.user.email', 'carol@external.example');
    expect($response->json('data.token'))->toBeString();

    expect($user->fresh()->tokens()->count())->toBe(1);
});

it(GOOGLE_ENDPOINT.' signs in a returning google customer', function () {
    googleSignIn('alice@gmail.com', true, GoogleEmailClass::GMAIL)->assertOk();
    googleSignIn('alice@gmail.com', true, GoogleEmailClass::GMAIL)->assertOk();

    // One token per device: the second sign-in replaced the first.
    $user = User::query()->where('email', 'alice@gmail.com')->firstOrFail();
    expect($user->tokens()->count())->toBe(1)
        ->and($user->tokens()->first()->name)->toBe('test-device')
        ->and(SocialAccount::query()->count())->toBe(1);
});

it(GOOGLE_ENDPOINT.' merges the guest cart via the cart token header', function () {
    $cartToken = (string) Str::uuid();

    Event::fake([UserSignedInEvent::class]);

    googleSignIn('alice@gmail.com', true, GoogleEmailClass::GMAIL, ['X-Cart-Token' => $cartToken])
        ->assertOk();

    Event::assertDispatched(UserSignedInEvent::class, fn (UserSignedInEvent $event) => $event->cart_token === $cartToken);
});

it(GOOGLE_ENDPOINT.' registers a new device once and only notifies for new devices', function () {
    Event::fake([NewDeviceSignInEvent::class]);

    googleSignIn('alice@gmail.com', true, GoogleEmailClass::GMAIL)->assertOk();
    Event::assertDispatched(NewDeviceSignInEvent::class, 1);

    // Same device again — no second new-device event.
    googleSignIn('alice@gmail.com', true, GoogleEmailClass::GMAIL)->assertOk();
    Event::assertDispatched(NewDeviceSignInEvent::class, 1);
});

it(GOOGLE_ENDPOINT.' refuses to link an existing password account (409, nothing mutated)', function () {
    User::factory()->create(['email' => 'alice@gmail.com', 'is_active' => true]);
    $usersBefore = User::query()->count();
    $socialsBefore = SocialAccount::query()->count();

    googleSignIn('alice@gmail.com', true, GoogleEmailClass::GMAIL, ['sub' => 'someone-else'])
        ->assertStatus(409)
        ->assertJsonPath('code', 'auth.google.email_exists');

    expect(User::query()->count())->toBe($usersBefore)
        ->and(SocialAccount::query()->count())->toBe($socialsBefore);
});

it(GOOGLE_ENDPOINT.' rejects an administrative account linked to the identity without mutating anything', function () {
    $admin = $this->superAdminUser();
    SocialAccount::query()->create([
        'user_id' => $admin->id,
        'provider' => 'google',
        'provider_user_id' => 'google-admin-sub',
    ]);
    $usersBefore = User::query()->count();
    $socialsBefore = SocialAccount::query()->count();

    /** @var FakeGoogleVerifier $fake */
    $fake = app(GoogleIdTokenVerifier::class);
    $fake->identity = googleIdentity($admin->email, true, GoogleEmailClass::GMAIL, sub: 'google-admin-sub');
    $fake->nonce = app(SignInNonceService::class)->issue();
    $nonce = $fake->nonce;

    $this->postJson($this->baseUrl(GOOGLE_ENDPOINT), ['credential' => 'fake-credential-0123456789'])
        ->assertForbidden()
        ->assertJsonPath('code', 'auth.google.admin_forbidden');

    expect(User::query()->count())->toBe($usersBefore)
        ->and(SocialAccount::query()->count())->toBe($socialsBefore)
        // Rejection consumed the nonce; it is not restored on failure.
        ->and(app(SignInNonceService::class)->consume($nonce))->toBeFalse();
});

it(GOOGLE_ENDPOINT.' rejects an arbitrary non-super-admin role holder', function () {
    $user = User::factory()->create(['is_active' => true]);
    $role = Role::query()->create(['name' => 'staff', 'guard_name' => 'web']);
    $user->assignRole($role);
    SocialAccount::query()->create(['user_id' => $user->id, 'provider' => 'google', 'provider_user_id' => 'staff-sub']);

    /** @var FakeGoogleVerifier $fake */
    $fake = app(GoogleIdTokenVerifier::class);
    $fake->identity = googleIdentity($user->email, true, GoogleEmailClass::GMAIL, sub: 'staff-sub');
    $fake->nonce = app(SignInNonceService::class)->issue();

    $this->postJson($this->baseUrl(GOOGLE_ENDPOINT), ['credential' => 'fake-credential-0123456789'])
        ->assertForbidden()
        ->assertJsonPath('code', 'auth.google.admin_forbidden');
});

it(GOOGLE_ENDPOINT.' rejects a user holding a direct permission', function () {
    $user = User::factory()->create(['is_active' => true]);
    $permission = Permission::query()->create(['name' => 'some-capability', 'guard_name' => 'web']);
    $user->givePermissionTo($permission);
    SocialAccount::query()->create(['user_id' => $user->id, 'provider' => 'google', 'provider_user_id' => 'perm-sub']);

    /** @var FakeGoogleVerifier $fake */
    $fake = app(GoogleIdTokenVerifier::class);
    $fake->identity = googleIdentity($user->email, true, GoogleEmailClass::GMAIL, sub: 'perm-sub');
    $fake->nonce = app(SignInNonceService::class)->issue();

    $this->postJson($this->baseUrl(GOOGLE_ENDPOINT), ['credential' => 'fake-credential-0123456789'])
        ->assertForbidden()
        ->assertJsonPath('code', 'auth.google.admin_forbidden');
});

it(GOOGLE_ENDPOINT.' reports an email conflict for an administrative account without mutating it', function () {
    $admin = $this->superAdminUser();
    $usersBefore = User::query()->count();
    $socialsBefore = SocialAccount::query()->count();

    googleSignIn($admin->email, true, GoogleEmailClass::GMAIL, ['sub' => 'unrelated-sub'])
        ->assertForbidden()
        ->assertJsonPath('code', 'auth.google.admin_forbidden');

    expect(User::query()->count())->toBe($usersBefore)
        ->and(SocialAccount::query()->count())->toBe($socialsBefore);
});

it(GOOGLE_ENDPOINT.' lets a locked linked account still request activation OTP but not obtain a token', function () {
    config(['auth.lockout.max_attempts' => 3]);

    // Active, linked customer.
    googleSignIn('alice@gmail.com', true, GoogleEmailClass::GMAIL)->assertOk();

    // Lock the account through the password path (the same boundary
    // issueToken enforces on the Google path).
    foreach (range(1, 3) as $i) {
        $this->postJson($this->baseUrl('/public/auth/signin'), [
            'email' => 'alice@gmail.com',
            'password' => 'wrong-password-'.$i,
        ])->assertUnauthorized();
    }

    googleSignIn('alice@gmail.com', true, GoogleEmailClass::GMAIL)
        ->assertStatus(429)
        ->assertJsonPath('code', 'auth.sign_in.locked');
});

it(GOOGLE_ENDPOINT.' resends the activation OTP for an inactive linked account without creating a second user', function () {
    $email = 'carol@external.example';
    $identity = googleIdentity($email, false, GoogleEmailClass::THIRD_PARTY);

    googleSignIn($email, false, GoogleEmailClass::THIRD_PARTY)->assertOk();

    // The initial signup minted a fresh OTP; age it past the 60-second
    // resend window so the return attempt may mint another one.
    DB::table('auth_otp_codes')->update(['created_at' => now()->subMinutes(2)]);

    Event::fake([OtpRequestedEvent::class, UserSignedInEvent::class, UserSignedUpEvent::class]);

    // Returning attempt for the still-inactive account: resend, same user.
    /** @var FakeGoogleVerifier $fake */
    $fake = app(GoogleIdTokenVerifier::class);
    $fake->identity = $identity;
    $fake->nonce = app(SignInNonceService::class)->issue();

    $this->postJson($this->baseUrl(GOOGLE_ENDPOINT), ['credential' => 'fake-credential-0123456789'])
        ->assertOk()
        ->assertJsonPath('data.requires_activation', true);

    Event::assertDispatched(OtpRequestedEvent::class, 1);
    // Activation resend never emits sign-in or signup events.
    Event::assertNotDispatched(UserSignedInEvent::class);
    Event::assertNotDispatched(UserSignedUpEvent::class);

    expect(User::query()->where('email', $email)->count())->toBe(1);
});

it(GOOGLE_ENDPOINT.' throttles activation resend through the existing OTP rules', function () {
    $email = 'carol@external.example';

    googleSignIn($email, false, GoogleEmailClass::THIRD_PARTY)->assertOk();

    /** @var FakeGoogleVerifier $fake */
    $fake = app(GoogleIdTokenVerifier::class);
    $fake->identity = googleIdentity($email, false, GoogleEmailClass::THIRD_PARTY);
    $fake->nonce = app(SignInNonceService::class)->issue();

    // Immediate second attempt hits the 60-second resend throttle.
    $this->postJson($this->baseUrl(GOOGLE_ENDPOINT), ['credential' => 'fake-credential-0123456789'])
        ->assertStatus(400)
        ->assertJsonPath('code', 'auth.otp.too_many_requests');
});

it(GOOGLE_ENDPOINT.' returns the disabled error on both endpoints when the feature is off', function () {
    config(['auth.google.enabled' => false]);

    $this->postJson($this->baseUrl(GOOGLE_ENDPOINT), ['credential' => 'x'.str_repeat('a', 20)])
        ->assertForbidden()
        ->assertJsonPath('code', 'auth.google.disabled');

    $this->postJson($this->baseUrl(GOOGLE_NONCE_ENDPOINT))
        ->assertForbidden()
        ->assertJsonPath('code', 'auth.google.disabled');
});

it(GOOGLE_ENDPOINT.' rate-limits the credential endpoint', function () {
    config(['auth.rate_limit.sign_in' => 1]);

    /** @var FakeGoogleVerifier $fake */
    $fake = app(GoogleIdTokenVerifier::class);
    $fake->throw = AuthException::googleInvalidToken();

    $this->postJson($this->baseUrl(GOOGLE_ENDPOINT), ['credential' => 'fake-credential-0123456789'])->assertStatus(401);
    $this->postJson($this->baseUrl(GOOGLE_ENDPOINT), ['credential' => 'fake-credential-0123456789'])->assertStatus(429);
});

it(GOOGLE_NONCE_ENDPOINT.' rate-limits and issues a single-use nonce', function () {
    config(['auth.rate_limit.sign_in' => 1]);

    $this->postJson($this->baseUrl(GOOGLE_NONCE_ENDPOINT))
        ->assertOk()
        ->assertJsonPath('data.nonce', fn ($nonce) => is_string($nonce) && strlen($nonce) >= 40);

    $this->postJson($this->baseUrl(GOOGLE_NONCE_ENDPOINT))->assertStatus(429);
});

it(GOOGLE_ENDPOINT.' keeps dispatch isolation: a failing signup event does not suppress the OTP event', function () {
    Event::listen(UserSignedUpEvent::class, fn () => throw new RuntimeException('signup listener exploded'));
    $otpDispatched = false;
    Event::listen(OtpRequestedEvent::class, function () use (&$otpDispatched) {
        $otpDispatched = true;
    });
    Log::spy();

    googleSignIn('carol@external.example', false, GoogleEmailClass::THIRD_PARTY)
        ->assertOk()
        ->assertJsonPath('data.requires_activation', true);

    // The second event was still attempted, exactly once.
    expect($otpDispatched)->toBeTrue();
    Log::shouldHaveReceived('warning')->once();
});

it(GOOGLE_ENDPOINT.' logs both failures independently when every dispatch fails', function () {
    Event::listen(UserSignedUpEvent::class, fn () => throw new RuntimeException('signup listener exploded'));
    Event::listen(OtpRequestedEvent::class, fn () => throw new RuntimeException('otp listener exploded'));
    Log::spy();

    googleSignIn('carol@external.example', false, GoogleEmailClass::THIRD_PARTY)
        ->assertOk()
        ->assertJsonPath('data.requires_activation', true);

    Log::shouldHaveReceived('warning')->twice();
});

it(GOOGLE_ENDPOINT.' keeps sign-in dispatch isolation: a failing sign-in event does not suppress the new-device event', function () {
    Event::listen(UserSignedInEvent::class, fn () => throw new RuntimeException('sign-in listener exploded'));
    $newDeviceDispatched = false;
    Event::listen(NewDeviceSignInEvent::class, function () use (&$newDeviceDispatched) {
        $newDeviceDispatched = true;
    });
    Log::spy();

    $response = googleSignIn('alice@gmail.com', true, GoogleEmailClass::GMAIL);
    $response->assertOk();

    // The eligible new-device event was still attempted, exactly once.
    expect($newDeviceDispatched)->toBeTrue();

    // The committed token still works.
    $this->getJson($this->baseUrl('/user/account/me'), ['Authorization' => 'Bearer '.$response->json('data.token')])
        ->assertOk();

    Log::shouldHaveReceived('warning')->once();
});

it(GOOGLE_ENDPOINT.' rolls back the whole third-party signup when the transaction fails', function () {
    // Real recording listeners (Event::fake would swallow model events):
    // afterCommit closures are discarded on rollback, so nothing dispatches.
    $dispatched = [];
    foreach ([UserSignedUpEvent::class, OtpRequestedEvent::class] as $eventClass) {
        Event::listen($eventClass, fn () => $dispatched[] = $eventClass);
    }

    GoogleSignInRollbackProbe::$enabled = true;
    SocialAccount::created(function () {
        if (GoogleSignInRollbackProbe::$enabled) {
            throw new RuntimeException('forced rollback');
        }
    });

    try {
        googleSignIn('carol@external.example', false, GoogleEmailClass::THIRD_PARTY)->assertInternalServerError();
    } finally {
        GoogleSignInRollbackProbe::$enabled = false;
    }

    // Nothing persisted, nothing dispatched (afterCommit discarded).
    expect(User::query()->count())->toBe(0)
        ->and(SocialAccount::query()->count())->toBe(0)
        ->and(DB::table('auth_otp_codes')->count())->toBe(0)
        ->and($dispatched)->toBe([]);
});

it(GOOGLE_ENDPOINT.' rolls back the whole instant signup including the token when the transaction fails', function () {
    $dispatched = [];
    Event::listen(UserSignedInEvent::class, fn () => $dispatched[] = UserSignedInEvent::class);

    GoogleSignInRollbackProbe::$enabled = true;
    SocialAccount::created(function () {
        if (GoogleSignInRollbackProbe::$enabled) {
            throw new RuntimeException('forced rollback');
        }
    });

    try {
        googleSignIn('alice@gmail.com', true, GoogleEmailClass::GMAIL)->assertInternalServerError();
    } finally {
        GoogleSignInRollbackProbe::$enabled = false;
    }

    expect(User::query()->count())->toBe(0)
        ->and(SocialAccount::query()->count())->toBe(0)
        ->and(DB::table('personal_access_tokens')->count())->toBe(0)
        ->and(DB::table('auth_user_devices')->count())->toBe(0)
        ->and($dispatched)->toBe([]);
});

it('/public/auth/signup still propagates dispatch exceptions (pre-extraction behavior)', function () {
    Event::listen(UserSignedUpEvent::class, fn () => throw new RuntimeException('signup listener exploded'));

    $this->postJson($this->baseUrl('/public/auth/signup'), [
        'email' => 'dave@example.com',
        'password' => 'Password123',
        'password_confirmation' => 'Password123',
    ])->assertInternalServerError();

    // Persistence happened exactly as before: user + OTP stored.
    expect(User::query()->where('email', 'dave@example.com')->count())->toBe(1)
        ->and(DB::table('auth_otp_codes')->count())->toBe(1);
});

/**
 * Test double standing in for the real verifier: it follows the same
 * contract — consume the presented nonce AFTER "verification", before the
 * identity is returned — so feature tests exercise real nonce behavior.
 */
class FakeGoogleVerifier extends GoogleIdTokenVerifier
{
    public ?GoogleIdentity $identity = null;

    public ?AuthException $throw = null;

    public ?string $nonce = null;

    public function __construct(private readonly \Illuminate\Contracts\Container\Container $app)
    {
        parent::__construct($app->make(SignInNonceService::class));
    }

    public function verify(string $credential, string $clientId): GoogleIdentity
    {
        if ($this->throw !== null) {
            throw $this->throw;
        }

        if ($this->identity === null) {
            throw AuthException::googleInvalidToken();
        }

        if ($this->nonce !== null && $this->app->make(SignInNonceService::class)->consume($this->nonce) === false) {
            throw AuthException::googleNonceInvalid();
        }

        return $this->identity;
    }
}
