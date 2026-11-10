<?php

namespace Modules\Notification\Listeners\Order\CreditNoteReady;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Storage;
use Modules\Core\Contracts\Events\Order\CreditNoteReadyEvent;
use Modules\Core\Contracts\Gateways\Auth\AccessGatewayInterface;
use Modules\Notification\Schemas\Notification\NotificationCodeEnum;
use Modules\Notification\Services\Notification\Contracts\NotificationDispatcherInterface;
use Modules\Notification\Services\Notification\DTOs\NotificationMessage;
use Throwable;

/**
 * "Your credit note is ready" with the stored PDF attached (gap-B1
 * Phase 4) — mirrors the invoice-ready listener exactly: queued, reads
 * only event data, failure-isolated from the accounting pipeline.
 */
class SendCreditNoteReadyToUserListener implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly NotificationDispatcherInterface $notificationDispatcher,
        private readonly AccessGatewayInterface $accessGateway,
    ) {}

    public function handle(CreditNoteReadyEvent $event): void
    {
        $email = $this->accessGateway->getUserEmailsByIds([$event->user_id])[$event->user_id] ?? null;

        $attachments = [];
        try {
            $bytes = Storage::disk($event->pdf_disk)->get($event->pdf_path);
            if ($bytes !== null) {
                $attachments[] = [
                    'data' => $bytes,
                    'name' => $event->credit_note_number.'.pdf',
                    'mime' => 'application/pdf',
                ];
            }
        } catch (Throwable $exception) {
            report($exception);
        }

        $placeholders = [
            'order_code' => $event->order_code,
            'credit_note_number' => $event->credit_note_number,
            'invoice_number' => $event->invoice_number,
        ];

        $message = new NotificationMessage(
            code: NotificationCodeEnum::ORDER_CREDIT_NOTE_READY->value,
            audience: 'user',
            recipients: [
                'database' => [$event->user_id],
                'email' => is_null($email) ? [] : [$email],
            ],
            data: [
                'database' => $placeholders,
                'email' => $placeholders,
            ],
            meta: ['order_code' => $event->order_code],
            locale: $event->locale,
            attachments: $attachments,
        );

        $this->notificationDispatcher->dispatch($message);
    }
}
