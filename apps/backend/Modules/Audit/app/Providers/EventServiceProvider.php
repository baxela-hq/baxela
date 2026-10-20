<?php

namespace Modules\Audit\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Modules\Audit\Listeners\RecordAuditLogListener;
use Modules\Core\Contracts\Events\Auth\UserActivatedEvent;
use Modules\Core\Contracts\Events\Auth\UserDeactivatedEvent;
use Modules\Core\Contracts\Events\Catalog\ProductActivatedEvent;
use Modules\Core\Contracts\Events\Catalog\ProductCommentApprovedEvent;
use Modules\Core\Contracts\Events\Catalog\ProductCreatedEvent;
use Modules\Core\Contracts\Events\Catalog\ProductDeactivatedEvent;
use Modules\Core\Contracts\Events\Catalog\ProductDeletedEvent;
use Modules\Core\Contracts\Events\Catalog\ProductUpdatedEvent;
use Modules\Core\Contracts\Events\Contact\ContactMessageDeletedEvent;
use Modules\Core\Contracts\Events\Contact\ContactMessageStatusUpdatedEvent;
use Modules\Core\Contracts\Events\Content\PagePublishedEvent;
use Modules\Core\Contracts\Events\Content\PageUnpublishedEvent;
use Modules\Core\Contracts\Events\Content\PostCommentApprovedEvent;
use Modules\Core\Contracts\Events\Inventory\StockDecreasedEvent;
use Modules\Core\Contracts\Events\Inventory\StockDepletedEvent;
use Modules\Core\Contracts\Events\Inventory\StockIncreasedEvent;
use Modules\Core\Contracts\Events\Media\MediaCreatedEvent;
use Modules\Core\Contracts\Events\Media\MediaDeletedEvent;
use Modules\Core\Contracts\Events\Menu\MenuCreatedEvent;
use Modules\Core\Contracts\Events\Menu\MenuDeletedEvent;
use Modules\Core\Contracts\Events\Menu\MenuLinkCreatedEvent;
use Modules\Core\Contracts\Events\Menu\MenuLinkDeletedEvent;
use Modules\Core\Contracts\Events\Menu\MenuLinkUpdatedEvent;
use Modules\Core\Contracts\Events\Menu\MenuUpdatedEvent;
use Modules\Core\Contracts\Events\Order\OrderCancelledEvent;
use Modules\Core\Contracts\Events\Order\OrderCompletedEvent;
use Modules\Core\Contracts\Events\Order\OrderPendingEvent;
use Modules\Core\Contracts\Events\Order\OrderRefundedEvent;
use Modules\Core\Contracts\Events\Order\OrderReturnApprovedEvent;
use Modules\Core\Contracts\Events\Order\OrderReturnCompletedEvent;
use Modules\Core\Contracts\Events\Order\OrderReturnReceivedEvent;
use Modules\Core\Contracts\Events\Order\OrderReturnRejectedEvent;
use Modules\Core\Contracts\Events\Payment\PaymentRefundedEvent;
use Modules\Core\Contracts\Events\Order\OrderShippedEvent;
use Modules\Core\Contracts\Events\Setting\SettingUpdatedEvent;
use Modules\Core\Contracts\Events\Shipping\ShipmentCreatedEvent;
use Modules\Core\Contracts\Events\Shipping\ShipmentDeliveredEvent;
use Modules\Core\Contracts\Events\Shipping\ShipmentShippedEvent;
use Modules\Core\Contracts\Events\Support\TicketDeletedEvent;
use Modules\Core\Contracts\Events\Support\TicketStatusUpdatedEvent;

class EventServiceProvider extends ServiceProvider
{
    /**
     * Admin-mutation event contracts mapped to the audit recorder. Customer-
     * triggered events (sign-ups, checkouts, public form posts) are excluded:
     * v1 audits what admin users do to the platform. Adding coverage for a
     * new event is a one-line map entry — the listener derives module,
     * entity, action and entity id from the event class itself.
     *
     * @var array<string, array<int, string>>
     */
    protected $listen = [
        UserActivatedEvent::class => [RecordAuditLogListener::class],
        UserDeactivatedEvent::class => [RecordAuditLogListener::class],

        ProductCreatedEvent::class => [RecordAuditLogListener::class],
        ProductUpdatedEvent::class => [RecordAuditLogListener::class],
        ProductDeletedEvent::class => [RecordAuditLogListener::class],
        ProductActivatedEvent::class => [RecordAuditLogListener::class],
        ProductDeactivatedEvent::class => [RecordAuditLogListener::class],
        ProductCommentApprovedEvent::class => [RecordAuditLogListener::class],

        ContactMessageDeletedEvent::class => [RecordAuditLogListener::class],
        ContactMessageStatusUpdatedEvent::class => [RecordAuditLogListener::class],

        PagePublishedEvent::class => [RecordAuditLogListener::class],
        PageUnpublishedEvent::class => [RecordAuditLogListener::class],
        PostCommentApprovedEvent::class => [RecordAuditLogListener::class],

        StockIncreasedEvent::class => [RecordAuditLogListener::class],
        StockDecreasedEvent::class => [RecordAuditLogListener::class],
        StockDepletedEvent::class => [RecordAuditLogListener::class],

        MediaCreatedEvent::class => [RecordAuditLogListener::class],
        MediaDeletedEvent::class => [RecordAuditLogListener::class],

        MenuCreatedEvent::class => [RecordAuditLogListener::class],
        MenuUpdatedEvent::class => [RecordAuditLogListener::class],
        MenuDeletedEvent::class => [RecordAuditLogListener::class],
        MenuLinkCreatedEvent::class => [RecordAuditLogListener::class],
        MenuLinkUpdatedEvent::class => [RecordAuditLogListener::class],
        MenuLinkDeletedEvent::class => [RecordAuditLogListener::class],

        OrderPendingEvent::class => [RecordAuditLogListener::class],
        OrderCancelledEvent::class => [RecordAuditLogListener::class],
        OrderCompletedEvent::class => [RecordAuditLogListener::class],
        OrderShippedEvent::class => [RecordAuditLogListener::class],
        OrderRefundedEvent::class => [RecordAuditLogListener::class],
        // return events triggered by the CUSTOMER (requested/shipped/
        // cancelled) stay out of the audit trail, like other public actions
        OrderReturnApprovedEvent::class => [RecordAuditLogListener::class],
        OrderReturnRejectedEvent::class => [RecordAuditLogListener::class],
        OrderReturnReceivedEvent::class => [RecordAuditLogListener::class],
        OrderReturnCompletedEvent::class => [RecordAuditLogListener::class],
        PaymentRefundedEvent::class => [RecordAuditLogListener::class],

        SettingUpdatedEvent::class => [RecordAuditLogListener::class],

        ShipmentCreatedEvent::class => [RecordAuditLogListener::class],
        ShipmentShippedEvent::class => [RecordAuditLogListener::class],
        ShipmentDeliveredEvent::class => [RecordAuditLogListener::class],

        TicketDeletedEvent::class => [RecordAuditLogListener::class],
        TicketStatusUpdatedEvent::class => [RecordAuditLogListener::class],
    ];

    /**
     * Discovery must stay off: the generic listener hints on the abstract
     * base event, which auto-discovery cannot resolve to real events.
     *
     * @var bool
     */
    protected static $shouldDiscoverEvents = false;
}
