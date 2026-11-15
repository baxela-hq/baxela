<?php

namespace Modules\Notification\Listeners\Order\OrderCancelled;

use Modules\Core\Contracts\Events\Order\OrderCancelledEvent;

class NotifyAdminOrderCancelledListener
{
    /**
     * Create the event listener.
     */
    public function __construct() {}

    /**
     * Handle the event.
     */
    public function handle(OrderCancelledEvent $event): void {}
}
