<?php

namespace Modules\Core\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Queue\Events\JobAttempted;
use Illuminate\Queue\Events\JobProcessing;
use Modules\Core\Listeners\QueueTenantLifecycle;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event handler mappings for the application.
     *
     * @var array<string, array<int, string>>
     */
    protected $listen = [
        // Queue lifecycle of the tenant context (multi-tenancy §6.1):
        // every attempt initializes from its payload and restores the
        // state it found — under the sync driver too, where the job runs
        // inside the request.
        JobProcessing::class => [QueueTenantLifecycle::class.'@processing'],
        JobAttempted::class => [QueueTenantLifecycle::class.'@attempted'],
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
