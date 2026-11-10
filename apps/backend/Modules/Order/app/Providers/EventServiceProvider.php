<?php

namespace Modules\Order\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Modules\Core\Contracts\Events\Order\OrderCancelledEvent;
use Modules\Core\Contracts\Events\Order\OrderCompletedEvent;
use Modules\Core\Contracts\Events\Order\OrderCreatedEvent;
use Modules\Core\Contracts\Events\Order\OrderPaidEvent;
use Modules\Core\Contracts\Events\Order\OrderPendingEvent;
use Modules\Core\Contracts\Events\Order\OrderRefundedEvent;
use Modules\Core\Contracts\Events\Order\OrderShippedEvent;
use Modules\Core\Contracts\Events\Payment\RefundFinalizedEvent;
use Modules\Core\Listeners\LogAllEvents;
use Modules\Order\Listeners\OrderPaid\IssueInvoiceOnOrderPaid;
use Modules\Order\Listeners\PaymentRefundFinalized\IssueCreditNoteOnRefundFinalized;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event handler mappings for the application.
     *
     * @var array<string, array<int, string>>
     */
    protected $listen = [
        OrderCancelledEvent::class => [LogAllEvents::class],
        OrderCompletedEvent::class => [LogAllEvents::class],
        OrderCreatedEvent::class => [LogAllEvents::class],
        OrderPaidEvent::class => [
            LogAllEvents::class,
            // Auto-issue (gap-B1 D9): idempotent, failure-isolated from
            // the paid pipeline itself.
            IssueInvoiceOnOrderPaid::class,
        ],
        // gap-B1 Phase 4 (D5): one credit note per finalized refund.
        RefundFinalizedEvent::class => [
            IssueCreditNoteOnRefundFinalized::class,
        ],
        OrderPendingEvent::class => [LogAllEvents::class],
        OrderRefundedEvent::class => [LogAllEvents::class],
        OrderShippedEvent::class => [LogAllEvents::class],
    ];

    /**
     * Indicates if events should be discovered.
     *
     * @var bool
     */
    protected static $shouldDiscoverEvents = true;

    /**
     * Configure the proper event listeners for email verification.
     */
    protected function configureEmailVerification(): void {}
}
