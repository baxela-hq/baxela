<?php

namespace Modules\Notification\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Realtime mirror of a freshly inserted database notification.
 *
 * Broadcast per recipient on their private channel so the admin and
 * storefront bells update without polling. The payload matches the
 * NotificationResource API shape plus the recipient's unread_count
 * (computed after the insert), so clients refresh the badge from the
 * event alone.
 */
class NotificationCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  array<string, mixed>  $payload  NotificationResource-shaped row
     */
    public function __construct(
        public readonly int $notifiableId,
        public readonly array $payload,
        public readonly int $unreadCount,
    ) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.'.$this->notifiableId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'notification.created';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return $this->payload + [
            'unread_count' => $this->unreadCount,
        ];
    }
}
