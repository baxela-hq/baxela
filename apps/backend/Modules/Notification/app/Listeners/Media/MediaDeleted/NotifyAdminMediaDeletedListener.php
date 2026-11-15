<?php

namespace Modules\Notification\Listeners\Media\MediaDeleted;

use Modules\Core\Contracts\Events\Media\MediaDeletedEvent;

class NotifyAdminMediaDeletedListener
{
    /**
     * Create the event listener.
     */
    public function __construct() {}

    /**
     * Handle the event.
     */
    public function handle(MediaDeletedEvent $event): void {}
}
