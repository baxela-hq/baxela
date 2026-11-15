<?php

namespace Modules\Notification\Listeners\Catalog\ProductActivated;

use Modules\Core\Contracts\Events\Catalog\ProductActivatedEvent;

class SendProductAvailableNotificationToUserListener
{
    /**
     * Create the event listener.
     */
    public function __construct() {}

    /**
     * Handle the event.
     */
    public function handle(ProductActivatedEvent $event): void {}
}
