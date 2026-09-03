<?php

namespace Modules\Inventory\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Modules\Core\Contracts\Events\Inventory\StockDecreasedEvent;
use Modules\Core\Contracts\Events\Inventory\StockDepletedEvent;
use Modules\Core\Contracts\Events\Inventory\StockIncreasedEvent;
use Modules\Core\Contracts\Events\Order\OrderCancelledEvent;
use Modules\Core\Listeners\LogAllEvents;
use Modules\Inventory\Listeners\Order\OrderCancelled\RestoreStockListener;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event handler mappings for the application.
     *
     * @var array<string, array<int, string>>
     */
    protected $listen = [
        StockDecreasedEvent::class => [LogAllEvents::class],
        StockDepletedEvent::class => [LogAllEvents::class],
        StockIncreasedEvent::class => [LogAllEvents::class],
        OrderCancelledEvent::class => [RestoreStockListener::class],
    ];

    /**
     * Indicates if events should be discovered.
     *
     * @var bool
     */
    protected static $shouldDiscoverEvents = true;

    /**
     * Configure the proper event listeners for email verification.
     */
    protected function configureEmailVerification(): void {}
}
