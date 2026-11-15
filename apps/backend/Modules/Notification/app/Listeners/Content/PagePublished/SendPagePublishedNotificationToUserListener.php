<?php

namespace Modules\Notification\Listeners\Content\PagePublished;

use Modules\Core\Contracts\Events\Content\PagePublishedEvent;

class SendPagePublishedNotificationToUserListener
{
    /**
     * Create the event listener.
     */
    public function __construct() {}

    /**
     * Handle the event.
     */
    public function handle(PagePublishedEvent $event): void {}
}
