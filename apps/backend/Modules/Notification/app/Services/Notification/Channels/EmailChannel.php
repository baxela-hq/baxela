<?php

namespace Modules\Notification\Services\Notification\Channels;

use Illuminate\Support\Facades\Log;
use Modules\Notification\Services\Notification\Contracts\MessageInterface;
use Modules\Notification\Services\Notification\Contracts\NotificationChannelInterface;
use Modules\Notification\Services\Notification\DTOs\Messages\EmailMessage;
use Modules\Notification\Services\Notification\DTOs\SendResult;

class EmailChannel implements NotificationChannelInterface
{
    public function send(MessageInterface $message): SendResult
    {
        if (! $message instanceof EmailMessage) {
            throw new \InvalidArgumentException('Invalid message type');
        }

        Log::info('Notification sent '.__CLASS__, $message->toArray());

        return new SendResult(success: true);
    }
}
