<?php

namespace Modules\Notification\Services\Notification\Channels;

use Illuminate\Support\Facades\Log;
use Modules\Notification\Events\NotificationCreated;
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

            $this->broadcastCreated((int) $recipient, $record);
        }

        Log::info('Notification sent '.__CLASS__, $message->toArray());

        return new SendResult(success: true);
    }

    /**
     * Push the new row to the recipient's private channel, in the same
     * shape the list endpoints return. The broadcast is queued like any
     * other ShouldBroadcast event, so a down websocket server never
     * blocks the insert.
     */
    private function broadcastCreated(int $recipientId, Notification $record): void
    {
        $code = $record->{NotificationSchema::CODE};

        NotificationCreated::dispatch(
            notifiableId: $recipientId,
            payload: [
                NotificationSchema::ID => $record->{NotificationSchema::ID},
                NotificationSchema::CODE => $code instanceof \BackedEnum ? $code->value : $code,
                NotificationSchema::TITLE => $record->{NotificationSchema::TITLE},
                NotificationSchema::BODY => $record->{NotificationSchema::BODY},
                NotificationSchema::META => $record->{NotificationSchema::META},
                NotificationSchema::READ_AT => $record->{NotificationSchema::READ_AT},
                NotificationSchema::CREATED_AT => $record->{NotificationSchema::CREATED_AT},
            ],
            unreadCount: Notification::query()
                ->where(NotificationSchema::USER_ID, $recipientId)
                ->whereNull(NotificationSchema::READ_AT)
                ->count(),
        );
    }
}
