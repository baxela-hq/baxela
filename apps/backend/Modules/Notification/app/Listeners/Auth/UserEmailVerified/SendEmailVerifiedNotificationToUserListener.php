<?php

namespace Modules\Notification\Listeners\Auth\UserEmailVerified;

use Modules\Core\Contracts\Events\Auth\UserEmailVerifiedEvent;

class SendEmailVerifiedNotificationToUserListener
{
    /**
     * Create the event listener.
     */
    public function __construct() {}

    /**
     * Handle the event.
     */
    public function handle(UserEmailVerifiedEvent $event): void {}
}
