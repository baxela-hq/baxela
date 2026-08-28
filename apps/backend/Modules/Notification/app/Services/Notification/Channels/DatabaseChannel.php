<?php

namespace Modules\Notification\Services\Notification\Channels;

use Illuminate\Support\Facades\Log;
use Modules\Notification\Models\Notification;
use Modules\Notification\Schemas\Notification\NotificationSchema;
use Modules\Notification\Services\Notification\Contracts\MessageInterface;
use Modules\Notification\Services\Notification\Contracts\NotificationChannelInterface;
use Modules\Notification\Services\Notification\DTOs\Messages\DatabaseMessage;
use Modules\Notification\Services\Notification\DTOs\SendResult;

class DatabaseChannel implements NotificationChannelInterface
{
    public function send(MessageInterface $message): SendResult
    {
        if (! $message instanceof DatabaseMessage) {
            throw new \InvalidArgumentException('Invalid message type');
        }

        $ids = [];
        foreach ($message->recipients as $recipient) {
            $record = Notification::query()->create([
                NotificationSchema::USER_ID => $recipient,
                NotificationSchema::CODE => $message->code,
                NotificationSchema::AUDIENCE => $message->audience,
                NotificationSchema::TITLE => $message->title,
                NotificationSchema::BODY => $message->body,
                NotificationSchema::META => empty($message->meta) ? null : $message->meta,
            ]);
            $ids[] = $record->{NotificationSchema::ID};
        }

        Log::info('Notification sent '.__CLASS__, $message->toArray());

        return new SendResult(success: true);
    }
}
