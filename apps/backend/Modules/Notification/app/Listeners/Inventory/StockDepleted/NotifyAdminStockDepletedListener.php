<?php

namespace Modules\Notification\Listeners\Inventory\StockDepleted;

use Modules\Core\Contracts\Events\Inventory\StockDepletedEvent;

class NotifyAdminStockDepletedListener
{
    /**
     * Create the event listener.
     */
    public function __construct() {}

    /**
     * Handle the event.
     */
    public function handle(StockDepletedEvent $event): void {}
}
