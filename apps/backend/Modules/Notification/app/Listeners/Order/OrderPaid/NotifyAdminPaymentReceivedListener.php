<?php

namespace Modules\Notification\Listeners\Order\OrderPaid;

use Modules\Core\Contracts\Events\Order\OrderPaidEvent;

class NotifyAdminPaymentReceivedListener
{
    /**
     * Create the event listener.
     */
    public function __construct() {}

    /**
     * Handle the event.
     */
    public function handle(OrderPaidEvent $event): void {}
}
