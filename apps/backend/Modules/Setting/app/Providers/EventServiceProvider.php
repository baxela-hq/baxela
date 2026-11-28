<?php

namespace Modules\Setting\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Modules\Core\Contracts\Events\Setting\SettingUpdatedEvent;
use Modules\Core\Listeners\LogAllEvents;
use Modules\Core\Support\Tenancy\TenantResolved;
use Modules\Setting\Listeners\ApplyTenantRuntime;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event handler mappings for the application.
     *
     * @var array<string, array<int, string>>
     */
    protected $listen = [
        SettingUpdatedEvent::class => [LogAllEvents::class],
        TenantResolved::class => [ApplyTenantRuntime::class],
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
