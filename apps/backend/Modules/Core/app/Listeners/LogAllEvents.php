<?php

namespace Modules\Core\Listeners;

use Illuminate\Support\Facades\Log;
use Modules\Core\Contracts\Events\AbstractBaseEvent;

class LogAllEvents
{
    /**
     * Create the event listener.
     */
    public function __construct() {}

    /**
     * Handle the event.
     */
    public function handle($event): void
    {
        if (config('core.log_requests') && $event instanceof AbstractBaseEvent) {
            Log::info('Event Fired', ['event' => get_class($event), 'payload' => $event->toArray()]);
        }
    }
}
