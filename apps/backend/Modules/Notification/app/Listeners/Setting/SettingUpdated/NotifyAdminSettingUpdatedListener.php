<?php

namespace Modules\Notification\Listeners\Setting\SettingUpdated;

use Modules\Core\Contracts\Events\Setting\SettingUpdatedEvent;

class NotifyAdminSettingUpdatedListener
{
    /**
     * Create the event listener.
     */
    public function __construct() {}

    /**
     * Handle the event.
     */
    public function handle(SettingUpdatedEvent $event): void {}
}
