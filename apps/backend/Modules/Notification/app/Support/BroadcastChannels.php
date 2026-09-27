<?php

namespace Modules\Notification\Support;

use Illuminate\Support\Facades\Broadcast;

/**
 * Channel authorization rules for the realtime notification stream.
 *
 * Called from the module service provider at boot. Kept as a discrete
 * register() so tests can re-bind the closures to a real broadcaster
 * connection (the null driver used by the suite accepts everything).
 */
class BroadcastChannels
{
    /**
     * Admin staff and customers are all users, so both audience-scoped
     * channel shapes authorize the same way: the id is checked against
     * the sanctum-authenticated user. The audience segment keeps each
     * app's realtime stream limited to its own rows — one user id can
     * hold both admin and storefront notifications.
     */
    public static function register(): void
    {
        $owner = fn ($user, string|int $id) => (int) $user->id === (int) $id;

        Broadcast::channel('notification.admin.{id}', $owner);
        Broadcast::channel('notification.user.{id}', $owner);
    }
}
