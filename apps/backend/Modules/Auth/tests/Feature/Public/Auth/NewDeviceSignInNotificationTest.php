<?php

use Illuminate\Events\CallQueuedListener;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Modules\Auth\Actions\Public\Auth\CompleteAuthenticationAction;
use Modules\Auth\Models\User;
use Modules\Auth\Models\UserDevice;
use Modules\Auth\Schemas\User\UserSchema;
use Modules\Auth\Schemas\UserDevice\UserDeviceSchema;
use Modules\Auth\Support\TwoFactor\TwoFactorService;
use Modules\Auth\Tests\Feature\HelperTrait;
use Modules\Core\Contracts\Events\Auth\NewDeviceSignInEvent;
use Modules\Notification\Emails\DynamicNotification;
use Modules\Notification\Listeners\Auth\NewDeviceSignIn\SendNewDeviceSignInNotificationListener;
use Modules\Notification\Models\Notification;
use Modules\Notification\Models\NotificationPreference;
use Modules\Notification\Schemas\Notification\NotificationAudienceEnum;
use Modules\Notification\Schemas\Notification\NotificationChannelEnum;
use Modules\Notification\Schemas\Notification\NotificationCodeEnum;
use Modules\Notification\Schemas\Notification\NotificationPreferenceSchema;
use Modules\Notification\Schemas\Notification\NotificationSchema;
use PragmaRX\Google2FA\Google2FA;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

const ND_SIGN_IN = '/public/auth/signin';

function ndUser(string $password = 'Passw0rd!'): User
{
    return User::factory()->create([
        UserSchema::PASSWORD => $password,
        UserSchema::IS_ACTIVE => true,
    ]);
}

/**
 * @return array{0: User, 1: string, 2: Google2FA}
 */
function ndEnrolledUser(): array
{
    $g2fa = app(Google2FA::class);
    $secret = $g2fa->generateSecretKey(32);
    $codes = app(TwoFactorService::class)->generateRecoveryCodes();

    $user = ndUser('12345678');
    $user->fill([
        UserSchema::TWO_FACTOR_SECRET => $secret,
        UserSchema::TWO_FACTOR_RECOVERY_CODES => app(TwoFactorService::class)->hashRecoveryCodes($codes),
        UserSchema::TWO_FACTOR_CONFIRMED_AT => now(),
    ]);
    $user->save();

    return [$user, $secret, $g2fa];
}

function ndSignIn(User $user, string $device, string $password = 'Passw0rd!'): TestResponse
{
    return test()->postJson(test()->baseUrl(ND_SIGN_IN), [
        UserSchema::EMAIL => $user->{UserSchema::EMAIL},
        UserSchema::PASSWORD => $password,
        'device_name' => $device,
    ]);
}

function ndNewDeviceNotificationCount(): int
{
    return Notification::query()
        ->where(NotificationSchema::CODE, NotificationCodeEnum::AUTH_USER_NEW_DEVICE->value)
        ->count();
}

it('fires one new-device notification (database + email) on the first sign-in from a device', function () {
    Mail::fake();
    $user = ndUser();

    $response = ndSignIn($user, 'laptop')->assertOk();

    expect($response->json('data.token'))->not->toBeNull()
        ->and(ndNewDeviceNotificationCount())->toBe(1);

    $row = Notification::query()
        ->where(NotificationSchema::CODE, NotificationCodeEnum::AUTH_USER_NEW_DEVICE->value)
        ->firstOrFail();
    expect((int) $row->{NotificationSchema::USER_ID})->toBe($user->id)
        ->and($row->{NotificationSchema::AUDIENCE})->toBe(NotificationAudienceEnum::USER)
        ->and($row->{NotificationSchema::TITLE})->not->toBeNull()
        ->and($row->{NotificationSchema::BODY})->toContain('laptop');

    Mail::assertSent(DynamicNotification::class, fn (DynamicNotification $mail): bool => $mail->hasTo($user->email));
});

it('does not re-notify for the same device and updates last_seen_at only', function () {
    Mail::fake();
    $user = ndUser();

    ndSignIn($user, 'laptop')->assertOk();
    $device = UserDevice::query()->where(UserDeviceSchema::DEVICE_NAME, 'laptop')->firstOrFail();
    $firstSeen = $device->{UserDeviceSchema::FIRST_SEEN_AT};
    expect(ndNewDeviceNotificationCount())->toBe(1);

    $this->travel(2)->hours();
    ndSignIn($user, 'laptop')->assertOk();

    $device->refresh();
    expect($device->{UserDeviceSchema::FIRST_SEEN_AT}->equalTo($firstSeen))->toBeTrue()
        ->and($device->{UserDeviceSchema::LAST_SEEN_AT}->gt($firstSeen))->toBeTrue()
        ->and(ndNewDeviceNotificationCount())->toBe(1);

    Mail::assertSent(DynamicNotification::class, 1);
});

it('notifies again for a different reported device name', function () {
    Mail::fake();
    $user = ndUser();

    ndSignIn($user, 'laptop')->assertOk();
    ndSignIn($user, 'phone')->assertOk();

    expect(UserDevice::query()->where(UserDeviceSchema::USER_ID, $user->id)->count())->toBe(2)
        ->and(ndNewDeviceNotificationCount())->toBe(2);
});

it('does not notify after a sign-out on the same device (device memory survives token deletion)', function () {
    Mail::fake();
    $user = ndUser();

    ndSignIn($user, 'laptop')->assertOk();
    // Sign-out deletes the current token; forced revocation deletes all
    // of them — both leave the device record intact, so no re-notify.
    $user->tokens()->delete();

    ndSignIn($user, 'laptop')->assertOk();

    expect(UserDevice::query()->where(UserDeviceSchema::DEVICE_NAME, 'laptop')->count())->toBe(1)
        ->and(ndNewDeviceNotificationCount())->toBe(1);
});

it('fires through the 2FA completion path on the first device', function () {
    Mail::fake();
    [$user, $secret, $g2fa] = ndEnrolledUser();

    $challengeToken = ndSignIn($user, 'laptop', '12345678')->assertOk()->json('data.challenge_token');
    expect(ndNewDeviceNotificationCount())->toBe(0); // no token yet — no sign-in

    test()->postJson(test()->baseUrl('/public/auth/2fa/verify'), [
        'challenge_token' => $challengeToken,
        'code' => $g2fa->getCurrentOtp($secret),
    ])->assertOk();

    expect(ndNewDeviceNotificationCount())->toBe(1)
        ->and(UserDevice::query()->where(UserDeviceSchema::DEVICE_NAME, 'laptop')->count())->toBe(1);
});

it('registers one device row for two racing first registrations of the same unseen device', function () {
    Mail::fake();
    $user = ndUser();
    $completion = app(CompleteAuthenticationAction::class);
    $context = ['device_name' => 'racing-device', 'remember' => true, 'cart_token' => null];

    // Deterministic shape of the concurrent double registration (F3):
    // insertOrIgnore arbitrates, the second registration must not count
    // as new.
    $first = $completion->issueToken($user, $context);
    $completion->dispatchEvents($user, $context, $first['device_is_new']);
    $second = $completion->issueToken($user, $context);
    $completion->dispatchEvents($user, $context, $second['device_is_new']);

    expect($first['device_is_new'])->toBeTrue()
        ->and($second['device_is_new'])->toBeFalse()
        ->and(UserDevice::query()->where(UserDeviceSchema::DEVICE_NAME, 'racing-device')->count())->toBe(1)
        ->and(ndNewDeviceNotificationCount())->toBe(1);
});

it('captures the request locale on the new-device event', function () {
    Event::fake();
    $user = ndUser();

    test()->postJson(test()->baseUrl(ND_SIGN_IN), [
        UserSchema::EMAIL => $user->{UserSchema::EMAIL},
        UserSchema::PASSWORD => 'Passw0rd!',
        'device_name' => 'laptop',
    ], ['Accept-Language' => 'fa'])->assertOk();

    Event::assertDispatched(
        NewDeviceSignInEvent::class,
        fn (NewDeviceSignInEvent $event): bool => $event->locale === 'fa' && $event->device_name === 'laptop',
    );
});

it('is queued off-request so delivery failures cannot affect authentication', function () {
    Queue::fake();
    $user = ndUser();

    $response = ndSignIn($user, 'laptop')->assertOk();

    expect($response->json('data.token'))->not->toBeNull();

    // Queued event listeners are pushed wrapped in CallQueuedListener.
    Queue::assertPushed(CallQueuedListener::class, fn (CallQueuedListener $job): bool => $job->class === SendNewDeviceSignInNotificationListener::class);
});

it('cannot be muted by a disabled notification preference (locked code)', function () {
    Mail::fake();
    $user = ndUser();

    // A stray preference row must not mute the security alert (F8/D7).
    NotificationPreference::query()->create([
        NotificationPreferenceSchema::USER_ID => $user->id,
        NotificationPreferenceSchema::CODE => NotificationCodeEnum::AUTH_USER_NEW_DEVICE,
        NotificationPreferenceSchema::AUDIENCE => NotificationAudienceEnum::USER,
        NotificationPreferenceSchema::CHANNEL => NotificationChannelEnum::EMAIL,
        NotificationPreferenceSchema::IS_ENABLED => false,
    ]);

    ndSignIn($user, 'laptop')->assertOk();

    expect(ndNewDeviceNotificationCount())->toBe(1);

    Mail::assertSent(DynamicNotification::class, fn (DynamicNotification $mail): bool => $mail->hasTo($user->email));
});
