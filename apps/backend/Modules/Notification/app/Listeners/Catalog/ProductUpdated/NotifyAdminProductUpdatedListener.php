<?php

namespace Modules\Notification\Listeners\Catalog\ProductUpdated;

use Modules\Core\Contracts\Events\Catalog\ProductUpdatedEvent;

class NotifyAdminProductUpdatedListener
{
    /**
     * Create the event listener.
     */
    public function __construct() {}

    /**
     * Handle the event.
     */
    public function handle(ProductUpdatedEvent $event): void {}
}
