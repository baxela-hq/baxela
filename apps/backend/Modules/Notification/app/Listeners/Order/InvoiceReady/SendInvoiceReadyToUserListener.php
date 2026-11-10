<?php

namespace Modules\Notification\Listeners\Order\InvoiceReady;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Storage;
use Modules\Core\Contracts\Events\Order\InvoiceReadyEvent;
use Modules\Core\Contracts\Gateways\Auth\AccessGatewayInterface;
use Modules\Notification\Schemas\Notification\NotificationCodeEnum;
use Modules\Notification\Services\Notification\Contracts\NotificationDispatcherInterface;
use Modules\Notification\Services\Notification\DTOs\NotificationMessage;
use Throwable;

/**
 * "Your invoice is ready" with the STORED pdf attached (gap-B1 Phase 3).
 * Queued, so delivery retries independently of generation — and a
 * delivery failure never re-issues or re-numbers the document: this
 * listener only reads. Everything document-related arrives on the
 * event; nothing here reaches into the Order module.
 */
class SendInvoiceReadyToUserListener implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly NotificationDispatcherInterface $notificationDispatcher,
        private readonly AccessGatewayInterface $accessGateway,
    ) {}

    public function handle(InvoiceReadyEvent $event): void
    {
        $email = $this->accessGateway->getUserEmailsByIds([$event->user_id])[$event->user_id] ?? null;

        $attachments = [];
        try {
            $bytes = Storage::disk($event->pdf_disk)->get($event->pdf_path);
            if ($bytes !== null) {
                $attachments[] = [
                    'data' => $bytes,
                    'name' => $event->invoice_number.'.pdf',
                    'mime' => 'application/pdf',
                ];
            }
        } catch (Throwable $exception) {
            // Storage anomaly: still announce the document, minus the
            // attachment, and leave the trace for ops.
            report($exception);
        }

        $placeholders = [
            'order_code' => $event->order_code,
            'invoice_number' => $event->invoice_number,
        ];

        $message = new NotificationMessage(
            code: NotificationCodeEnum::ORDER_INVOICE_READY->value,
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
