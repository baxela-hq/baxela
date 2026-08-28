<?php

namespace Modules\Auth\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Modules\Core\Contracts\Events\Auth\UserDeactivatedEvent;
use Modules\Core\Contracts\Events\Auth\UserEmailVerifiedEvent;
use Modules\Core\Contracts\Events\Auth\UserSignedInEvent;
use Modules\Core\Contracts\Events\Auth\UserSignedUpEvent;
use Modules\Core\Listeners\LogAllEvents;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event handler mappings for the application.
     *
     * @var array<string, array<int, string>>
     */
    protected $listen = [
        UserDeactivatedEvent::class => [LogAllEvents::class],
        UserEmailVerifiedEvent::class => [LogAllEvents::class],
        UserSignedInEvent::class => [LogAllEvents::class],
        UserSignedUpEvent::class => [LogAllEvents::class],
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
