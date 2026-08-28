<?php

namespace Modules\Media\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Modules\Core\Contracts\Events\Media\MediaCreatedEvent;
use Modules\Core\Contracts\Events\Media\MediaDeletedEvent;
use Modules\Core\Listeners\LogAllEvents;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event handler mappings for the application.
     *
     * @var array<string, array<int, string>>
     */
    protected $listen = [
        MediaCreatedEvent::class => [LogAllEvents::class],
        MediaDeletedEvent::class => [LogAllEvents::class],
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
