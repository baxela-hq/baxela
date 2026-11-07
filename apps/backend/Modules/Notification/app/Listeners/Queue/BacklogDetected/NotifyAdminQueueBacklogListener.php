<?php

namespace Modules\Notification\Listeners\Queue\BacklogDetected;

use Illuminate\Support\Facades\Log;
use Modules\Core\Contracts\Events\System\QueueBacklogDetectedEvent;
use Modules\Notification\Schemas\Notification\NotificationCodeEnum;
use Modules\Notification\Services\Notification\Contracts\NotificationDispatcherInterface;
use Modules\Notification\Services\Notification\DTOs\NotificationMessage;
use Modules\Notification\Support\AdminRecipients;
use Throwable;

/**
 * Admin alert when the configured queue depth crosses its threshold
 * (gap-B3 D6) — depth monitoring, not throughput monitoring. Synchronous and
 * isolated, same contract as NotifyAdminJobFailedListener: identifiers only
 * on every channel, best-effort delivery, never rethrows.
 */
class NotifyAdminQueueBacklogListener
{
    public function __construct(
        private readonly NotificationDispatcherInterface $notificationDispatcher,
    ) {}

    /**
     * Handle the event.
     */
    public function handle(QueueBacklogDetectedEvent $event): void
    {
        try {
            $this->notificationDispatcher->dispatch($this->buildMessage($event));
        } catch (Throwable $e) {
            Log::error('Failed to dispatch the queue backlog admin alert.', [
                'queue' => $event->queue,
                'connection' => $event->connection,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function buildMessage(QueueBacklogDetectedEvent $event): NotificationMessage
    {
        $context = [
            'queue' => $event->queue,
            'connection' => $event->connection,
            'size' => $event->size,
            'threshold' => $event->threshold,
            'detected_at' => now()->toISOString(),
        ];

        return new NotificationMessage(
            code: NotificationCodeEnum::SYSTEM_QUEUE_BACKLOG->value,
            audience: 'admin',
            recipients: [
                'database' => AdminRecipients::databaseIds(),
                'email' => config('notification.notifications.admin_recipients.email', []),
            ],
            // Identifiers only on every channel — same safe content rule as
            // the job-failed alert; keys explicit for each channel (D3).
            data: [
                'database' => $context,
                'email' => $context,
                'telegram' => $context,
                'webpush' => $context,
            ],
            meta: [
                'queue' => $event->queue,
                'connection' => $event->connection,
                'size' => $event->size,
            ],
        );
    }
}
