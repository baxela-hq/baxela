<?php

namespace Modules\Notification\Services\Notification\Channels;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Modules\Notification\Emails\DynamicNotification;
use Modules\Notification\Services\Notification\Contracts\MessageInterface;
use Modules\Notification\Services\Notification\Contracts\NotificationChannelInterface;
use Modules\Notification\Services\Notification\DTOs\Messages\EmailMessage;
use Modules\Notification\Services\Notification\DTOs\SendResult;
use Throwable;

class EmailChannel implements NotificationChannelInterface
{
    public function send(MessageInterface $message): SendResult
    {
        if (! $message instanceof EmailMessage) {
            throw new \InvalidArgumentException('Invalid message type');
        }

        try {
            Mail::to($message->recipients)->send(new DynamicNotification(
                subject: $message->subject,
                body: $message->body,
                meta: $message->meta,
            ));
        } catch (Throwable $e) {
            report($e);
            Log::error('Failed to send notification email.', $message->toArray());

            return new SendResult(success: false);
        }

        return new SendResult(success: true);
    }
}
