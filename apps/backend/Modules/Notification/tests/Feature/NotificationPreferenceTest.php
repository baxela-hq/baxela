<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Modules\Auth\Models\User;
use Modules\Notification\Emails\DynamicNotification;
use Modules\Notification\Models\Notification;
use Modules\Notification\Models\NotificationPreference;
use Modules\Notification\Schemas\Notification\NotificationAudienceEnum;
use Modules\Notification\Schemas\Notification\NotificationChannelEnum;
use Modules\Notification\Schemas\Notification\NotificationCodeEnum;
use Modules\Notification\Schemas\Notification\NotificationPreferenceSchema;
use Modules\Notification\Schemas\Notification\NotificationSchema;
use Modules\Notification\Services\Notification\Contracts\NotificationDispatcherInterface;
use Modules\Notification\Services\Notification\DTOs\NotificationMessage;
use Modules\Notification\Tests\Feature\HelperTrait;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

function preferencePayload(string $code, string $channel, bool $isEnabled): array
{
    return [
        'code' => $code,
        'channel' => $channel,
        'is_enabled' => $isEnabled,
    ];
}

it('lists the admin-configurable notifications with channel defaults', function () {
    $this->actingAs($this->superAdminUser(), 'sanctum')
        ->getJson($this->baseUrl('/admin/notification-preferences'))
        ->assertOk()
        ->assertJsonFragment(['code' => 'order.order.created'])
        ->assertJsonFragment(['channel' => 'email', 'enabled' => true, 'is_default' => true])
        // user-audience-only codes are not configurable here
        ->assertJsonMissing(['code' => 'order.order.shipped']);
});

it('persists a per-channel toggle scoped to the caller', function () {
    $admin = $this->superAdminUser();

    $this->actingAs($admin, 'sanctum')
        ->patchJson($this->baseUrl('/admin/notification-preferences'), preferencePayload('order.order.created', 'email', false))
        ->assertOk()
        ->assertJsonFragment(['channel' => 'email', 'enabled' => false, 'is_default' => false]);

    $row = NotificationPreference::query()->sole();

    expect($row->{NotificationPreferenceSchema::USER_ID})
        ->toBe($admin->id)
        ->and($row->{NotificationPreferenceSchema::CODE})->toBe(NotificationCodeEnum::ORDER_ORDER_CREATED)
        ->and($row->{NotificationPreferenceSchema::CHANNEL})->toBe(NotificationChannelEnum::EMAIL)
        ->and($row->{NotificationPreferenceSchema::AUDIENCE})->toBe(NotificationAudienceEnum::ADMIN);
});

it('rejects codes outside the admin matrix and unconfigured channels', function () {
    $admin = $this->superAdminUser();

    // transactional, user-audience — never admin-configurable
    $this->actingAs($admin, 'sanctum')
        ->patchJson($this->baseUrl('/admin/notification-preferences'), preferencePayload('auth.user.otp_code', 'email', false))
        ->assertStatus(422);

    // payment.succeeded runs on database+webpush only
    $this->actingAs($admin, 'sanctum')
        ->patchJson($this->baseUrl('/admin/notification-preferences'), preferencePayload('payment.payment.succeeded', 'email', false))
        ->assertStatus(422);
});

it('requires the admin permission', function () {
    $customer = User::factory()->create();

    $this->actingAs($customer, 'sanctum')
        ->getJson($this->baseUrl('/admin/notification-preferences'))
        ->assertStatus(403);
});

it('drops recipients who disabled the code for a channel', function () {
    $muted = User::factory()->create();
    $other = User::factory()->create();

    NotificationPreference::query()->create([
        NotificationPreferenceSchema::USER_ID => $muted->id,
        NotificationPreferenceSchema::CODE => NotificationCodeEnum::ORDER_ORDER_CREATED,
        NotificationPreferenceSchema::AUDIENCE => NotificationAudienceEnum::ADMIN,
        NotificationPreferenceSchema::CHANNEL => NotificationChannelEnum::DATABASE,
        NotificationPreferenceSchema::IS_ENABLED => false,
    ]);

    app(NotificationDispatcherInterface::class)->dispatch(new NotificationMessage(
        code: NotificationCodeEnum::ORDER_ORDER_CREATED->value,
        audience: 'admin',
        recipients: ['database' => [$muted->id, $other->id]],
        data: ['database' => ['order_code' => 'BX-1', 'amount' => '10.00']],
    ));

    $recipients = Notification::query()
        ->where(NotificationSchema::CODE, NotificationCodeEnum::ORDER_ORDER_CREATED->value)
        ->where(NotificationSchema::AUDIENCE, 'admin')
        ->pluck(NotificationSchema::USER_ID);

    expect($recipients->all())->toBe([$other->id]);

    // the admin toggle must not silence the same person's customer copy
    app(NotificationDispatcherInterface::class)->dispatch(new NotificationMessage(
        code: NotificationCodeEnum::ORDER_ORDER_CREATED->value,
        audience: 'user',
        recipients: ['database' => [$muted->id]],
        data: ['database' => ['order_code' => 'BX-2', 'amount' => '10.00']],
    ));

    $customerCopy = Notification::query()
        ->where(NotificationSchema::CODE, NotificationCodeEnum::ORDER_ORDER_CREATED->value)
        ->where(NotificationSchema::AUDIENCE, 'user')
        ->pluck(NotificationSchema::USER_ID);

    expect($customerCopy->all())->toBe([$muted->id]);
});

it('still emails config-level addresses when a user mutes the code', function () {
    $muted = User::factory()->create();

    NotificationPreference::query()->create([
        NotificationPreferenceSchema::USER_ID => $muted->id,
        NotificationPreferenceSchema::CODE => NotificationCodeEnum::ORDER_ORDER_CREATED,
        NotificationPreferenceSchema::AUDIENCE => NotificationAudienceEnum::ADMIN,
        NotificationPreferenceSchema::CHANNEL => NotificationChannelEnum::EMAIL,
        NotificationPreferenceSchema::IS_ENABLED => false,
    ]);

    Mail::fake();

    app(NotificationDispatcherInterface::class)->dispatch(new NotificationMessage(
        code: NotificationCodeEnum::ORDER_ORDER_CREATED->value,
        audience: 'admin',
        recipients: ['email' => [$muted->email, 'ops@example.com']],
        data: ['email' => ['order_code' => 'BX-1', 'amount' => '10.00']],
    ));

    Mail::assertSent(DynamicNotification::class, fn (DynamicNotification $mail): bool => $mail->hasTo('ops@example.com') && ! $mail->hasTo($muted->email));
});

it('never filters locked codes', function () {
    $user = User::factory()->create();

    // a stray preference row must not be able to mute an OTP delivery
    NotificationPreference::query()->create([
        NotificationPreferenceSchema::USER_ID => $user->id,
        NotificationPreferenceSchema::CODE => NotificationCodeEnum::AUTH_USER_OTP_CODE,
        NotificationPreferenceSchema::AUDIENCE => NotificationAudienceEnum::USER,
        NotificationPreferenceSchema::CHANNEL => NotificationChannelEnum::EMAIL,
        NotificationPreferenceSchema::IS_ENABLED => false,
    ]);

    Mail::fake();

    app(NotificationDispatcherInterface::class)->dispatch(new NotificationMessage(
        code: NotificationCodeEnum::AUTH_USER_OTP_CODE->value,
        audience: 'user',
        recipients: ['email' => [$user->email]],
        data: ['email' => ['code' => '123456', 'action' => 'verify_email', 'expires_in' => 10]],
    ));

    Mail::assertSent(DynamicNotification::class, fn (DynamicNotification $mail): bool => $mail->hasTo($user->email));
});
