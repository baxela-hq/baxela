<?php

namespace Modules\Notification\Listeners\Content\PageUnpublished;

use Modules\Core\Contracts\Events\Content\PageUnpublishedEvent;

class NotifyAdminPageUnpublishedListener
{
    /**
     * Create the event listener.
     */
    public function __construct() {}

    /**
     * Handle the event.
     */
    public function handle(PageUnpublishedEvent $event): void {}
}
