<?php

namespace Modules\Notification\Listeners\User\UserProfileUpdated;

use Modules\Core\Contracts\Events\User\UserProfileUpdatedEvent;

class SendProfileUpdatedNotificationToUserListener
{
    /**
     * Create the event listener.
     */
    public function __construct() {}

    /**
     * Handle the event.
     */
    public function handle(UserProfileUpdatedEvent $event): void {}
}
