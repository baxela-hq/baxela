<?php

namespace Modules\Menu\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Modules\Core\Contracts\Events\Menu\MenuCreatedEvent;
use Modules\Core\Contracts\Events\Menu\MenuDeletedEvent;
use Modules\Core\Contracts\Events\Menu\MenuLinkCreatedEvent;
use Modules\Core\Contracts\Events\Menu\MenuLinkDeletedEvent;
use Modules\Core\Contracts\Events\Menu\MenuLinkUpdatedEvent;
use Modules\Core\Contracts\Events\Menu\MenuUpdatedEvent;
use Modules\Core\Listeners\LogAllEvents;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event handler mappings for the application.
     *
     * @var array<string, array<int, string>>
     */
    protected $listen = [
        MenuCreatedEvent::class => [LogAllEvents::class],
        MenuUpdatedEvent::class => [LogAllEvents::class],
        MenuDeletedEvent::class => [LogAllEvents::class],
        MenuLinkCreatedEvent::class => [LogAllEvents::class],
        MenuLinkUpdatedEvent::class => [LogAllEvents::class],
        MenuLinkDeletedEvent::class => [LogAllEvents::class],
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
