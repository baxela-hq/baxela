<?php

namespace Modules\Notification\Listeners\Payment\PaymentInitiated;

use Modules\Core\Contracts\Events\Payment\PaymentInitiatedEvent;

class SendPaymentInitiatedNotificationToUserListener
{
    /**
     * Create the event listener.
     */
    public function __construct() {}

    /**
     * Handle the event.
     */
    public function handle(PaymentInitiatedEvent $event): void {}
}
