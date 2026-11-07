<?php

namespace Modules\Webhooks\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Modules\Core\Contracts\Events\Order\OrderCancelledEvent;
use Modules\Core\Contracts\Events\Order\OrderCreatedEvent;
use Modules\Core\Contracts\Events\Order\OrderPaidEvent;
use Modules\Core\Contracts\Events\Order\OrderReturnRequestedEvent;
use Modules\Core\Contracts\Events\Order\OrderShippedEvent;
use Modules\Core\Contracts\Events\Payment\PaymentRefundedEvent;
use Modules\Core\Contracts\Events\Shipping\ShipmentShippedEvent;
use Modules\Webhooks\Listeners\RecordOrderCancelledListener;
use Modules\Webhooks\Listeners\RecordOrderCreatedListener;
use Modules\Webhooks\Listeners\RecordOrderPaidListener;
use Modules\Webhooks\Listeners\RecordOrderReturnRequestedListener;
use Modules\Webhooks\Listeners\RecordOrderShippedListener;
use Modules\Webhooks\Listeners\RecordPaymentRefundedListener;
use Modules\Webhooks\Listeners\RecordShipmentShippedListener;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event handler mappings for the application (gap-C2 D2): every
     * catalog event has one synchronous outbox listener. Discovery stays
     * off so this map is the single, explicit registration point.
     *
     * @var array<string, array<int, string>>
     */
    protected $listen = [
        OrderCreatedEvent::class => [RecordOrderCreatedListener::class],
        OrderPaidEvent::class => [RecordOrderPaidListener::class],
        OrderCancelledEvent::class => [RecordOrderCancelledListener::class],
        OrderShippedEvent::class => [RecordOrderShippedListener::class],
        ShipmentShippedEvent::class => [RecordShipmentShippedListener::class],
        PaymentRefundedEvent::class => [RecordPaymentRefundedListener::class],
        OrderReturnRequestedEvent::class => [RecordOrderReturnRequestedListener::class],
    ];

    /**
     * Indicates if events should be discovered.
     *
     * @var bool
     */
    protected static $shouldDiscoverEvents = false;

    /**
     * Configure the proper event listeners for email verification.
     */
    protected function configureEmailVerification(): void {}
}
