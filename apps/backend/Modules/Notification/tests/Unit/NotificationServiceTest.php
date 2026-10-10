<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Modules\Auth\Models\User;
use Modules\Notification\Emails\DynamicNotification;
use Modules\Notification\Models\Notification;
use Modules\Notification\Schemas\Notification\NotificationCodeEnum;
use Modules\Notification\Services\Notification\Contracts\NotificationDispatcherInterface;
use Modules\Notification\Services\Notification\DTOs\NotificationMessage;
use Modules\Notification\Tests\Feature\HelperTrait;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

it('dispatches a dual-channel notification to the database and email', function () {
    Mail::fake();

    $user = User::factory()->create();
    $data = ['post_id' => 42, 'excerpt' => 'Nice post'];

    app(NotificationDispatcherInterface::class)->dispatch(new NotificationMessage(
        code: NotificationCodeEnum::CONTENT_POST_COMMENT_APPROVED->value,
        audience: 'user',
        recipients: ['email' => [$user->email], 'database' => [$user->id]],
        data: ['database' => $data, 'email' => $data],
        locale: 'en',
    ));

    $row = Notification::query()
        ->where('user_id', $user->id)
        ->where('code', NotificationCodeEnum::CONTENT_POST_COMMENT_APPROVED->value)
        ->first();

    expect($row)->not->toBeNull()
        ->and($row->title)->toBe('Your comment was approved')
        ->and($row->body)->toBe('Good news — your comment "Nice post" on post 42 has been approved and is now visible.');

    Mail::assertSent(DynamicNotification::class, 1);
    Mail::assertSent(DynamicNotification::class, fn (DynamicNotification $mail) => $mail->hasTo($user->email));
});

it('renders OTP email placeholders in the app locale with an en/fa switch', function () {
    Mail::fake();

    $otpMessage = fn () => new NotificationMessage(
        code: NotificationCodeEnum::AUTH_USER_OTP_CODE->value,
        audience: 'user',
        recipients: ['email' => ['jane@example.com']],
        data: ['email' => [
            'code' => '842317',
            'action' => 'signup',
            'expires_in' => 5,
        ]],
    );

    app()->setLocale('en');
    app(NotificationDispatcherInterface::class)->dispatch($otpMessage());

    Mail::assertSent(DynamicNotification::class, fn (DynamicNotification $mail) => trim($mail->subject) === 'Your verification code'
        && str_contains($mail->body, '842317')
        && str_contains($mail->body, '5 minutes'));

    app()->setLocale('fa');
    app(NotificationDispatcherInterface::class)->dispatch($otpMessage());

    Mail::assertSent(DynamicNotification::class, 2);
    Mail::assertSent(DynamicNotification::class, fn (DynamicNotification $mail) => trim($mail->subject) === 'کد تایید شما'
        && str_contains($mail->body, '842317'));
});
