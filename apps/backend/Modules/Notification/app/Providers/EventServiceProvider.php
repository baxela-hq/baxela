<?php

namespace Modules\Notification\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Modules\Core\Contracts\Events\Auth\OtpRequestedEvent;
use Modules\Core\Contracts\Events\Auth\UserSignedInEvent;
use Modules\Core\Contracts\Events\Contact\ContactMessageCreatedEvent;
use Modules\Core\Contracts\Events\Order\OrderCancelledEvent;
use Modules\Core\Contracts\Events\Order\OrderCompletedEvent;
use Modules\Core\Contracts\Events\Order\OrderCreatedEvent;
use Modules\Core\Contracts\Events\Order\OrderPaidEvent;
use Modules\Core\Contracts\Events\Order\OrderShippedEvent;
use Modules\Core\Contracts\Events\Payment\PaymentFailedEvent;
use Modules\Core\Contracts\Events\Payment\PaymentSucceededEvent;
use Modules\Notification\Listeners\Auth\OtpRequested\SendOtpCodeToUserListener;
use Modules\Notification\Listeners\Auth\UserSignedIn\SendNewLoginAlertToUserListener;
use Modules\Notification\Listeners\Contact\ContactMessageCreated\NotifyAdminContactMessageCreatedListener;
use Modules\Notification\Listeners\Order\OrderCancelled\SendOrderCancelledNotificationToUserListener;
use Modules\Notification\Listeners\Order\OrderCompleted\SendOrderCompletedNotificationToUserListener;
use Modules\Notification\Listeners\Order\OrderCreated\NotifyAdminNewOrderListener;
use Modules\Notification\Listeners\Order\OrderCreated\SendOrderConfirmationToUserListener;
use Modules\Notification\Listeners\Order\OrderPaid\SendPaymentReceivedNotificationToUserListener;
use Modules\Notification\Listeners\Order\OrderShipped\SendOrderShippedNotificationToUserListener;
use Modules\Notification\Listeners\Payment\PaymentFailed\NotifyAdminPaymentFailedListener;
use Modules\Notification\Listeners\Payment\PaymentFailed\SendPaymentFailedNotificationToUserListener;
use Modules\Notification\Listeners\Payment\PaymentSucceeded\NotifyAdminPaymentSucceededListener;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event handler mappings for the application.
     *
     * @var array<string, array<int, string>>
     */
    protected $listen = [
        UserSignedInEvent::class => [
            SendNewLoginAlertToUserListener::class,
        ],
        OtpRequestedEvent::class => [
            SendOtpCodeToUserListener::class,
        ],
        ContactMessageCreatedEvent::class => [
            NotifyAdminContactMessageCreatedListener::class,
        ],
        OrderCreatedEvent::class => [
            NotifyAdminNewOrderListener::class,
            SendOrderConfirmationToUserListener::class,
        ],
        OrderPaidEvent::class => [
            SendPaymentReceivedNotificationToUserListener::class,
        ],
        OrderShippedEvent::class => [
            SendOrderShippedNotificationToUserListener::class,
        ],
        OrderCompletedEvent::class => [
            SendOrderCompletedNotificationToUserListener::class,
        ],
        OrderCancelledEvent::class => [
            SendOrderCancelledNotificationToUserListener::class,
        ],
        PaymentSucceededEvent::class => [
            NotifyAdminPaymentSucceededListener::class,
        ],
        PaymentFailedEvent::class => [
            SendPaymentFailedNotificationToUserListener::class,
            NotifyAdminPaymentFailedListener::class,
        ],
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
