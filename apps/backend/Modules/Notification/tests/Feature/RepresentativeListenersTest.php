<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Modules\Auth\Models\User;
use Modules\Core\Contracts\Events\Auth\OtpRequestedEvent;
use Modules\Core\Contracts\Events\Auth\UserSignedInEvent;
use Modules\Notification\Emails\DynamicNotification;
use Modules\Notification\Models\Notification;
use Modules\Notification\Schemas\Notification\NotificationCodeEnum;
use Modules\Notification\Tests\Feature\HelperTrait;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

it('emails the OTP code when an OTP is requested', function () {
    Mail::fake();

    $user = User::factory()->create();

    event(OtpRequestedEvent::fill([
        'email' => $user->email,
        'code' => '842317',
        'action' => 'forgot_password',
    ]));

    Mail::assertSent(DynamicNotification::class, 1);
    Mail::assertSent(DynamicNotification::class, function (DynamicNotification $mail) use ($user): bool {
        return $mail->hasTo($user->email)
            && str_contains($mail->body, '842317')
            && str_contains($mail->body, 'reset your password');
    });
});

it('records a database notification and email on sign-in', function () {
    Mail::fake();

    $user = User::factory()->create();

    event(new UserSignedInEvent(
        $user->id,
        $user->email,
        now()->toDateTimeString(),
    ));

    $row = Notification::query()
        ->where('user_id', $user->id)
        ->where('code', NotificationCodeEnum::AUTH_USER_SIGNED_IN->value)
        ->first();

    expect($row)->not->toBeNull()
        ->and($row->title)->toBe('New Login to your account');

    Mail::assertSent(DynamicNotification::class, 1);
});
