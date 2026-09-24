<?php

namespace Modules\Discount\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Modules\Core\Contracts\Events\Discount\ShipmentCreatedEvent;
use Modules\Core\Contracts\Events\Discount\ShipmentDeliveredEvent;
use Modules\Core\Contracts\Events\Discount\ShipmentShippedEvent;
use Modules\Core\Listeners\LogAllEvents;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event handler mappings for the application.
     *
     * @var array<string, array<int, string>>
     */
    protected $listen = [
        ShipmentCreatedEvent::class => [LogAllEvents::class],
        ShipmentShippedEvent::class => [LogAllEvents::class],
        ShipmentDeliveredEvent::class => [LogAllEvents::class],
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
