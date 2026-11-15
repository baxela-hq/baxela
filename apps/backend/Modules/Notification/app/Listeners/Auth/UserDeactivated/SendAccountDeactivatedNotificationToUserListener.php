<?php

namespace Modules\Notification\Listeners\Auth\UserDeactivated;

use Modules\Core\Contracts\Events\Auth\UserDeactivatedEvent;

class SendAccountDeactivatedNotificationToUserListener
{
    /**
     * Create the event listener.
     */
    public function __construct() {}

    /**
     * Handle the event.
     */
    public function handle(UserDeactivatedEvent $event): void {}
}
