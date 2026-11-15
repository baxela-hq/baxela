<?php

namespace Modules\Notification\Listeners\Catalog\ProductCreated;

use Modules\Core\Contracts\Events\Catalog\ProductCreatedEvent;

class NotifyAdminProductCreatedListener
{
    /**
     * Create the event listener.
     */
    public function __construct() {}

    /**
     * Handle the event.
     */
    public function handle(ProductCreatedEvent $event): void {}
}
