<?php

namespace Modules\Notification\Listeners\Catalog\ProductDeactivated;

use Modules\Core\Contracts\Events\Catalog\ProductDeactivatedEvent;

class NotifyAdminProductDeactivatedListener
{
    /**
     * Create the event listener.
     */
    public function __construct() {}

    /**
     * Handle the event.
     */
    public function handle(ProductDeactivatedEvent $event): void {}
}
