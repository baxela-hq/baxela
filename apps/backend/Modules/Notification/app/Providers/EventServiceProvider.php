<?php

namespace Modules\Notification\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Modules\Core\Contracts\Events\Auth\OtpRequestedEvent;
use Modules\Core\Contracts\Events\Auth\UserSignedInEvent;
use Modules\Core\Contracts\Events\Contact\ContactMessageCreatedEvent;
use Modules\Notification\Listeners\Auth\OtpRequested\SendOtpCodeToUserListener;
use Modules\Notification\Listeners\Auth\UserSignedIn\SendNewLoginAlertToUserListener;
use Modules\Notification\Listeners\Contact\ContactMessageCreated\NotifyAdminContactMessageCreatedListener;

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
        ContactMessageCreatedEvent::class => [
            NotifyAdminContactMessageCreatedListener::class,
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
