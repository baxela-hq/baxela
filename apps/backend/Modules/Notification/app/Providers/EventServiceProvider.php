<?php

namespace Modules\Notification\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Modules\Core\Contracts\Events\Auth\OtpRequestedEvent;
use Modules\Core\Contracts\Events\Auth\UserSignedInEvent;
use Modules\Notification\Listeners\Auth\OtpRequested\SendOtpCodeToUserListener;
use Modules\Notification\Listeners\Auth\UserSignedIn\SendNewLoginAlertToUserListener;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event handler mappings for the application.
     *
     * @var array<string, array<int, string>>
     */
    protected $listen = [
        UserSignedInEvent::class => [
            SendNewLoginAlertToUserListener::class,
        ],
        OtpRequestedEvent::class => [
            SendOtpCodeToUserListener::class,
        ],
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
