<?php

namespace Modules\Notification\Listeners\Order\OrderShipped;

use Modules\Core\Contracts\Events\Order\OrderShippedEvent;

class NotifyAdminOrderShippedListener
{
    /**
     * Create the event listener.
     */
    public function __construct() {}

    /**
     * Handle the event.
     */
    public function handle(OrderShippedEvent $event): void {}
}
