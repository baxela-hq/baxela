<?php

namespace Modules\Cart\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Modules\Core\Contracts\Events\Cart\CartCheckedOutEvent;
use Modules\Core\Contracts\Events\Cart\CartCreatedEvent;
use Modules\Core\Contracts\Events\Cart\CartItemAddedEvent;
use Modules\Core\Contracts\Events\Cart\CartItemRemovedEvent;
use Modules\Core\Listeners\LogAllEvents;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event handler mappings for the application.
     *
     * @var array<string, array<int, string>>
     */
    protected $listen = [
        CartCheckedOutEvent::class => [LogAllEvents::class],
        CartCreatedEvent::class => [LogAllEvents::class],
        CartItemAddedEvent::class => [LogAllEvents::class],
        CartItemRemovedEvent::class => [LogAllEvents::class],
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
