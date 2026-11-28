<?php

use Modules\Core\Support\Dispatch\SanctionedDispatch;
use Modules\Notification\Services\Notification\Contracts\NotificationDispatcherInterface;

/**
 * The dispatch registry (postgres-migration 3.3.0a / 3.3.0): the
 * reviewed, machine-checked inventory of every deferred-work mechanism -
 * REQUIRED entries carry a durable in-transaction record (outbox or
 * journal) so delivery survives a commit-to-dispatch crash; BEST-EFFORT
 * entries accept loss (notifications/emails). The architecture test
 * (tests/Feature/Architecture/DispatchRegistryTest.php) fails the build
 * for any ShouldQueue class or variable-target dispatch absent from this
 * registry or the sanctioned wrappers - new required side effects cannot
 * land without durable backing, and the registry diff is reviewed like a
 * permission grant.
 *
 * Statically undiscoverable mechanisms (dynamic class names, variable or
 * facade bus calls, string handlers) MUST route through
 * SanctionedDispatch (Modules/Core/Support/Dispatch) or the existing
 * NotificationDispatcherInterface boundary instead of raw dispatch.
 */
return [
    'queued' => [
        'Modules\\Catalog\\Listeners\\DeindexProductOnProductDeleted' => [
            'classification' => 'required',
            'durable_record' => 'catalog_search_operations journal (in-transaction SearchCursorManager::append)',
            'processor' => 'ProductIndexWriter + catalog:search:reconcile',
            'retry' => 'journal replay; nightly reindex backstop',
        ],
        'Modules\\Catalog\\Listeners\\IndexProductOnProductChanged' => [
            'classification' => 'required',
            'durable_record' => 'catalog_search_operations journal (in-transaction SearchCursorManager::append)',
            'processor' => 'ProductIndexWriter + catalog:search:reconcile',
            'retry' => 'journal replay; nightly reindex backstop',
        ],
        'Modules\\Core\\Support\\Dispatch\\SanctionedDispatch' => [
            'classification' => 'best-effort',
            'durable_record' => 'none (accepted loss - current semantics)',
            'processor' => 'queue workers',
            'retry' => 'queue tries',
        ],
        'Modules\\Media\\Listeners\\Media\\GenerateImageVariantsListener' => [
            'classification' => 'required',
            'durable_record' => 'media row + media_outbox entry (one transaction)',
            'processor' => 'media:outbox:process',
            'retry' => 'outbox claim/redelivery; media:outbox:sweep cleanup',
        ],
        'Modules\\Notification\\Listeners\\Auth\\NewDeviceSignIn\\SendNewDeviceSignInNotificationListener' => [
            'classification' => 'best-effort',
            'durable_record' => 'none (accepted loss - current semantics)',
            'processor' => 'queue workers',
            'retry' => 'queue tries',
        ],
        'Modules\\Notification\\Listeners\\Content\\PostCommentApproved\\SendPostCommentApprovedToUserListener' => [
            'classification' => 'best-effort',
            'durable_record' => 'none (accepted loss - current semantics)',
            'processor' => 'queue workers',
            'retry' => 'queue tries',
        ],
        'Modules\\Notification\\Listeners\\Content\\PostCommentCreated\\NotifyAdminPostCommentCreatedListener' => [
            'classification' => 'best-effort',
            'durable_record' => 'none (accepted loss - current semantics)',
            'processor' => 'queue workers',
            'retry' => 'queue tries',
        ],
        'Modules\\Notification\\Listeners\\Order\\CreditNoteReady\\SendCreditNoteReadyToUserListener' => [
            'classification' => 'best-effort',
            'durable_record' => 'none (accepted loss - current semantics)',
            'processor' => 'queue workers',
            'retry' => 'queue tries',
        ],
        'Modules\\Notification\\Listeners\\Order\\InvoiceReady\\SendInvoiceReadyToUserListener' => [
            'classification' => 'best-effort',
            'durable_record' => 'none (accepted loss - current semantics)',
            'processor' => 'queue workers',
            'retry' => 'queue tries',
        ],
        'Modules\\Notification\\Listeners\\Order\\OrderCancelled\\SendOrderCancelledNotificationToUserListener' => [
            'classification' => 'best-effort',
            'durable_record' => 'none (accepted loss - current semantics)',
            'processor' => 'queue workers',
            'retry' => 'queue tries',
        ],
        'Modules\\Notification\\Listeners\\Order\\OrderCompleted\\SendOrderCompletedNotificationToUserListener' => [
            'classification' => 'best-effort',
            'durable_record' => 'none (accepted loss - current semantics)',
            'processor' => 'queue workers',
            'retry' => 'queue tries',
        ],
        'Modules\\Notification\\Listeners\\Order\\OrderCreated\\NotifyAdminNewOrderListener' => [
            'classification' => 'best-effort',
            'durable_record' => 'none (accepted loss - current semantics)',
            'processor' => 'queue workers',
            'retry' => 'queue tries',
        ],
        'Modules\\Notification\\Listeners\\Order\\OrderCreated\\SendOrderConfirmationToUserListener' => [
            'classification' => 'best-effort',
            'durable_record' => 'none (accepted loss - current semantics)',
            'processor' => 'queue workers',
            'retry' => 'queue tries',
        ],
        'Modules\\Notification\\Listeners\\Order\\OrderPaid\\SendPaymentReceivedNotificationToUserListener' => [
            'classification' => 'best-effort',
            'durable_record' => 'none (accepted loss - current semantics)',
            'processor' => 'queue workers',
            'retry' => 'queue tries',
        ],
        'Modules\\Notification\\Listeners\\Order\\OrderRefunded\\SendOrderRefundedNotificationToUserListener' => [
            'classification' => 'best-effort',
            'durable_record' => 'none (accepted loss - current semantics)',
            'processor' => 'queue workers',
            'retry' => 'queue tries',
        ],
        'Modules\\Notification\\Listeners\\Order\\OrderReturn\\NotifyAdminReturnRequestedListener' => [
            'classification' => 'best-effort',
            'durable_record' => 'none (accepted loss - current semantics)',
            'processor' => 'queue workers',
            'retry' => 'queue tries',
        ],
        'Modules\\Notification\\Listeners\\Order\\OrderReturn\\SendReturnApprovedToUserListener' => [
            'classification' => 'best-effort',
            'durable_record' => 'none (accepted loss - current semantics)',
            'processor' => 'queue workers',
            'retry' => 'queue tries',
        ],
        'Modules\\Notification\\Listeners\\Order\\OrderReturn\\SendReturnCompletedToUserListener' => [
            'classification' => 'best-effort',
            'durable_record' => 'none (accepted loss - current semantics)',
            'processor' => 'queue workers',
            'retry' => 'queue tries',
        ],
        'Modules\\Notification\\Listeners\\Order\\OrderReturn\\SendReturnReceivedToUserListener' => [
            'classification' => 'best-effort',
            'durable_record' => 'none (accepted loss - current semantics)',
            'processor' => 'queue workers',
            'retry' => 'queue tries',
        ],
        'Modules\\Notification\\Listeners\\Order\\OrderReturn\\SendReturnRejectedToUserListener' => [
            'classification' => 'best-effort',
            'durable_record' => 'none (accepted loss - current semantics)',
            'processor' => 'queue workers',
            'retry' => 'queue tries',
        ],
        'Modules\\Notification\\Listeners\\Order\\OrderShipped\\SendOrderShippedNotificationToUserListener' => [
            'classification' => 'best-effort',
            'durable_record' => 'none (accepted loss - current semantics)',
            'processor' => 'queue workers',
            'retry' => 'queue tries',
        ],
        'Modules\\Notification\\Listeners\\Payment\\PaymentFailed\\NotifyAdminPaymentFailedListener' => [
            'classification' => 'best-effort',
            'durable_record' => 'none (accepted loss - current semantics)',
            'processor' => 'queue workers',
            'retry' => 'queue tries',
        ],
        'Modules\\Notification\\Listeners\\Payment\\PaymentFailed\\SendPaymentFailedNotificationToUserListener' => [
            'classification' => 'best-effort',
            'durable_record' => 'none (accepted loss - current semantics)',
            'processor' => 'queue workers',
            'retry' => 'queue tries',
        ],
        'Modules\\Notification\\Listeners\\Payment\\PaymentSucceeded\\NotifyAdminPaymentSucceededListener' => [
            'classification' => 'best-effort',
            'durable_record' => 'none (accepted loss - current semantics)',
            'processor' => 'queue workers',
            'retry' => 'queue tries',
        ],
        'Modules\\Notification\\Listeners\\Queue\\JobFailed\\NotifyAdminJobFailedListener' => [
            'classification' => 'best-effort',
            'durable_record' => 'none (accepted loss - current semantics)',
            'processor' => 'queue workers',
            'retry' => 'queue tries',
        ],
        'Modules\\Notification\\Listeners\\Support\\TicketCreated\\NotifyAdminSupportTicketCreatedListener' => [
            'classification' => 'best-effort',
            'durable_record' => 'none (accepted loss - current semantics)',
            'processor' => 'queue workers',
            'retry' => 'queue tries',
        ],
        'Modules\\Notification\\Listeners\\Support\\TicketMessageCreated\\NotifyAdminSupportTicketRepliedListener' => [
            'classification' => 'best-effort',
            'durable_record' => 'none (accepted loss - current semantics)',
            'processor' => 'queue workers',
            'retry' => 'queue tries',
        ],
        'Modules\\Notification\\Listeners\\Support\\TicketMessageCreated\\SendTicketAnsweredNotificationToUserListener' => [
            'classification' => 'best-effort',
            'durable_record' => 'none (accepted loss - current semantics)',
            'processor' => 'queue workers',
            'retry' => 'queue tries',
        ],
        'Modules\\Webhooks\\Jobs\\DeliverWebhookJob' => [
            'classification' => 'required',
            'durable_record' => 'webhook_events/webhook_deliveries (in-transaction outbox)',
            'processor' => 'webhooks:dispatch-deliveries + retry command',
            'retry' => 'per-delivery retry schedule',
        ],
    ],

    // Broadcast events are queued through Illuminate's BroadcastEvent
    // wrapper (K7). Like notification listeners they are best-effort: the
    // authoritative notification row is inserted before the broadcast, so
    // a dropped websocket delivery only costs a realtime refresh — the
    // inbox and unread endpoints are the replay source.
    'broadcast' => [
        'Modules\\Notification\\Events\\NotificationCreated' => [
            'classification' => 'best-effort',
            'durable_record' => 'notifications row (authoritative; inserted before the broadcast)',
            'processor' => 'queue workers → Reverb broadcaster',
            'retry' => 'queue tries; inbox/unread endpoints are the replay source',
        ],
    ],

    // Observer registrations (->observe( / #[ObservedBy]): none today -
    // Modules/Catalog/app/Observers/VariantObserver.php exists but is
    // unwired; wiring it requires an entry here.
    'observers' => [
    ],

    // The sanctioned choke points for statically-undiscoverable dispatch:
    // variable-target dispatch is only legal through these.
    'sanctioned' => [
        SanctionedDispatch::class,
        NotificationDispatcherInterface::class,
    ],
];
