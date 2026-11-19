<?php

namespace Modules\Catalog\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Modules\Catalog\Listeners\DeindexProductOnProductDeleted;
use Modules\Catalog\Listeners\IndexProductOnProductChanged;
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
     * The index listeners ride the product events ONLY when the engine is
     * configured (host non-empty): with the engine off the whole search
     * subsystem is off — the journal entries written transactionally by
     * the mutation sites stay durable and the nightly `catalog:reindex`
     * covers them from the database.
     */
    public function register(): void
    {
        if ((string) config('catalog.search.host') !== '') {
            $this->listen[ProductCreatedEvent::class][] = IndexProductOnProductChanged::class;
            $this->listen[ProductUpdatedEvent::class][] = IndexProductOnProductChanged::class;
            $this->listen[ProductDeletedEvent::class][] = DeindexProductOnProductDeleted::class;
        }

        parent::register();
    }

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
