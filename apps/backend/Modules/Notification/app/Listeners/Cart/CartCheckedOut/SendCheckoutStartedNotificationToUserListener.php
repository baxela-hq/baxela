<?php

namespace Modules\Notification\Listeners\Cart\CartCheckedOut;

use Modules\Core\Contracts\Events\Cart\CartCheckedOutEvent;

class SendCheckoutStartedNotificationToUserListener
{
    /**
     * Create the event listener.
     */
    public function __construct() {}

    /**
     * Handle the event.
     */
    public function handle(CartCheckedOutEvent $event): void {}
}
