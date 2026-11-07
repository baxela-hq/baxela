<?php

namespace Modules\Notification\Listeners\Queue\JobFailed;

use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Log;
use Modules\Notification\Schemas\Notification\NotificationCodeEnum;
use Modules\Notification\Services\Notification\Contracts\NotificationDispatcherInterface;
use Modules\Notification\Services\Notification\DTOs\NotificationMessage;
use Modules\Notification\Support\AdminRecipients;
use Throwable;

/**
 * Admin alert when a queued job finally fails (gap-B3 D5).
 *
 * Deliberately NOT ShouldQueue: an alert about a broken queue must not
 * depend on a working queue. Best-effort by design — the whole handle is
 * wrapped so a notification failure never interferes with Laravel's own
 * failure handling and can never recurse into a second failure notification.
 * External channels (email/telegram/webpush) carry identifiers only; the
 * exception excerpt stays inside the admin's database-channel inbox (D3).
 */
class NotifyAdminJobFailedListener
{
    /** Exception excerpt length for the database inbox — D2's list bound. */
    private const int EXCEPTION_EXCERPT_LENGTH = 300;

    public function __construct(
        private readonly NotificationDispatcherInterface $notificationDispatcher,
    ) {}

    /**
     * Handle the event.
     */
    public function handle(JobFailed $event): void
    {
        try {
            $this->notificationDispatcher->dispatch($this->buildMessage($event));
        } catch (Throwable $e) {
            // An alert about a failure must never become a second failure.
            // Log identifiers only — never payload contents.
            Log::error('Failed to dispatch the failed-job admin alert.', [
                'uuid' => $this->safeUuid($event),
                'queue' => $this->safeQueue($event),
                'connection' => $event->connectionName,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function buildMessage(JobFailed $event): NotificationMessage
    {
        $context = $this->context($event);

        return new NotificationMessage(
            code: NotificationCodeEnum::SYSTEM_QUEUE_JOB_FAILED->value,
            audience: 'admin',
            recipients: [
                'database' => AdminRecipients::databaseIds(),
                'email' => config('notification.notifications.admin_recipients.email', []),
            ],
            data: [
                // Database inbox: identifiers + the exception excerpt.
                'database' => [
                    ...$context,
                    'exception_excerpt' => mb_substr($event->exception->getMessage(), 0, self::EXCEPTION_EXCERPT_LENGTH),
                ],
                // External channels: identifiers only — never the exception.
                // Always explicit keys: the telegram/webpush builders fall back
                // to data['database'], which would leak the excerpt (D3).
                'email' => $context,
                'telegram' => $context,
                'webpush' => $context,
            ],
            meta: [
                'uuid' => $context['uuid'],
                'queue' => $context['queue'],
                'connection' => $context['connection'],
            ],
        );
    }

    /**
     * Identifier context extracted defensively — a malformed payload must not
     * prevent the alert from being built.
     *
     * @return array{job: string, queue: string, connection: string, uuid: string, failed_at: string}
     */
    private function context(JobFailed $event): array
    {
        return [
            'job' => $this->safeJobName($event),
            'queue' => $this->safeQueue($event),
            'connection' => $event->connectionName,
            'uuid' => $this->safeUuid($event),
            'failed_at' => now()->toISOString(),
        ];
    }

    private function safeJobName(JobFailed $event): string
    {
        try {
            return (string) $event->job->resolveName();
        } catch (Throwable) {
            return 'unknown';
        }
    }

    private function safeQueue(JobFailed $event): string
    {
        try {
            return (string) $event->job->getQueue();
        } catch (Throwable) {
            return 'unknown';
        }
    }

    private function safeUuid(JobFailed $event): string
    {
        try {
            return (string) $event->job->uuid();
        } catch (Throwable) {
            return 'unknown';
        }
    }
}
