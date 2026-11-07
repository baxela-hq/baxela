<?php

namespace Modules\Webhooks\Listeners;

use Modules\Core\Contracts\Events\Order\OrderReturnRequestedEvent;
use Modules\Webhooks\Actions\Webhooks\ProcessOutboxEventAction;
use Modules\Webhooks\Actions\Webhooks\WriteOutboxEventAction;
use Modules\Webhooks\Jobs\DeliverWebhookJob;

/**
 * Synchronous outbox writer (gap-C2 D2): when the event fires inside a
 * business transaction, the insert joins it — crash-before-commit rolls
 * back both. Fast-path enqueue after commit; the scheduled sweep
 * recovers a lost enqueue (the delivery table is the source of truth).
 */
class RecordOrderReturnRequestedListener
{
    public function __construct(
        protected WriteOutboxEventAction $writer,
        protected ProcessOutboxEventAction $processor,
    ) {}

    public function handle(OrderReturnRequestedEvent $event): void
    {
        $outbox = $this->writer->write(
            type: 'return.requested',
            sourceKey: 'return.requested:'.$event->id,
            data: $event->toArray(),
        );

        if ($outbox->wasRecentlyCreated) {
            $this->processor->handle($outbox);

            $outbox->deliveries()->each(
                fn ($delivery) => DeliverWebhookJob::dispatch($delivery->id),
            );
        }
    }
}
