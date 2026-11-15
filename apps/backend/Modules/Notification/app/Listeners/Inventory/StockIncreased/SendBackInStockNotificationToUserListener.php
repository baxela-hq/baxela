<?php

namespace Modules\Notification\Listeners\Inventory\StockIncreased;

use Modules\Core\Contracts\Events\Inventory\StockIncreasedEvent;

class SendBackInStockNotificationToUserListener
{
    /**
     * Create the event listener.
     */
    public function __construct() {}

    /**
     * Handle the event.
     */
    public function handle(StockIncreasedEvent $event): void {}
}
