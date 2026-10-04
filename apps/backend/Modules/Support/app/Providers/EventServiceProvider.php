<?php

namespace Modules\Support\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Modules\Core\Contracts\Events\Support\TicketCreatedEvent;
use Modules\Core\Contracts\Events\Support\TicketDeletedEvent;
use Modules\Core\Contracts\Events\Support\TicketMessageCreatedEvent;
use Modules\Core\Contracts\Events\Support\TicketStatusUpdatedEvent;
use Modules\Core\Listeners\LogAllEvents;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event handler mappings for the application.
     *
     * @var array<string, array<int, string>>
     */
    protected $listen = [
        TicketCreatedEvent::class => [LogAllEvents::class],
        TicketMessageCreatedEvent::class => [LogAllEvents::class],
        TicketStatusUpdatedEvent::class => [LogAllEvents::class],
        TicketDeletedEvent::class => [LogAllEvents::class],
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
