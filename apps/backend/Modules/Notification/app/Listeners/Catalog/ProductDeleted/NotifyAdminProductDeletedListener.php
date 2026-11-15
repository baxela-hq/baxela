<?php

namespace Modules\Notification\Listeners\Catalog\ProductDeleted;

use Modules\Core\Contracts\Events\Catalog\ProductDeletedEvent;

class NotifyAdminProductDeletedListener
{
    /**
     * Create the event listener.
     */
    public function __construct() {}

    /**
     * Handle the event.
     */
    public function handle(ProductDeletedEvent $event): void {}
}
