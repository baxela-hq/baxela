<?php

namespace App\Octane;

use Laravel\Octane\Events\RequestReceived;
use Modules\Core\Extensions\ExtensionManager;

/**
 * Re-reads extension state at the start of every request so a persistent
 * Octane worker sees the same state a fresh FPM process would.
 *
 * Scope note: this only resets the ExtensionManager's cached rows. Service
 * providers boot once per worker — an extension enabled or disabled while a
 * worker is running keeps its registered bindings until `octane:reload` or
 * a container restart.
 */
class FlushExtensionState
{
    /**
     * Handle the event.
     *
     * @param  RequestReceived  $event
     */
    public function handle($event): void
    {
        ExtensionManager::flush();
    }
}
