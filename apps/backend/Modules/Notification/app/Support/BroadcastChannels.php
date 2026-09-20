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
     * Admin staff and customers are all users, so one private channel
     * shape serves both apps; the id is checked against the
     * sanctum-authenticated user.
     */
    public static function register(): void
    {
        Broadcast::channel(
            'user.{id}',
            fn ($user, string|int $id) => (int) $user->id === (int) $id
        );
    }
}
