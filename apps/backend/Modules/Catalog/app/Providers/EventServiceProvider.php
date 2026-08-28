<?php

namespace Modules\Catalog\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Modules\Core\Contracts\Events\Catalog\ProductActivatedEvent;
use Modules\Core\Contracts\Events\Catalog\ProductCreatedEvent;
use Modules\Core\Contracts\Events\Catalog\ProductDeactivatedEvent;
use Modules\Core\Contracts\Events\Catalog\ProductDeletedEvent;
use Modules\Core\Contracts\Events\Catalog\ProductUpdatedEvent;
use Modules\Core\Listeners\LogAllEvents;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event handler mappings for the application.
     *
     * @var array<string, array<int, string>>
     */
    protected $listen = [
        ProductActivatedEvent::class => [LogAllEvents::class],
        ProductCreatedEvent::class => [LogAllEvents::class],
        ProductDeactivatedEvent::class => [LogAllEvents::class],
        ProductDeletedEvent::class => [LogAllEvents::class],
        ProductUpdatedEvent::class => [LogAllEvents::class],
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
