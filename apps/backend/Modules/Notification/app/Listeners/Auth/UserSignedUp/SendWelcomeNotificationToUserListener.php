<?php

namespace Modules\Notification\Listeners\Auth\UserSignedUp;

use Modules\Core\Contracts\Events\Auth\UserSignedUpEvent;

class SendWelcomeNotificationToUserListener
{
    /**
     * Create the event listener.
     */
    public function __construct() {}

    /**
     * Handle the event.
     */
    public function handle(UserSignedUpEvent $event): void {}
}
