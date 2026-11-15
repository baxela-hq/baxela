<?php

namespace Modules\Notification\Listeners\Order\OrderPending;

use Modules\Core\Contracts\Events\Order\OrderPendingEvent;

class SendOrderPendingNotificationToUserListener
{
    /**
     * Create the event listener.
     */
    public function __construct() {}

    /**
     * Handle the event.
     */
    public function handle(OrderPendingEvent $event): void {}
}
