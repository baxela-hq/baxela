<?php

namespace Modules\Contact\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Modules\Core\Contracts\Events\Contact\ContactMessageCreatedEvent;
use Modules\Core\Contracts\Events\Contact\ContactMessageDeletedEvent;
use Modules\Core\Contracts\Events\Contact\ContactMessageStatusUpdatedEvent;
use Modules\Core\Listeners\LogAllEvents;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event handler mappings for the application.
     *
     * @var array<string, array<int, string>>
     */
    protected $listen = [
        ContactMessageCreatedEvent::class => [LogAllEvents::class],
        ContactMessageStatusUpdatedEvent::class => [LogAllEvents::class],
        ContactMessageDeletedEvent::class => [LogAllEvents::class],
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
