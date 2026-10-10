<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Modules\Auth\Models\User;
use Modules\Core\Contracts\Events\Auth\OtpRequestedEvent;
use Modules\Notification\Emails\DynamicNotification;
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
