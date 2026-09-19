<?php

namespace Modules\Notification\Support;

use Modules\Core\Contracts\Gateways\Auth\AccessGatewayInterface;

/**
 * Recipient ids for admin database notifications: the configured
 * ADMIN_NOTIFICATION_USER_IDS list when set, otherwise every active
 * staff account resolved through the access gateway.
 */
class AdminRecipients
{
    /**
     * @return array<int, int>
     */
    public static function databaseIds(): array
    {
        $configured = config('notification.notifications.admin_recipients.database', []);

        if (! empty($configured)) {
            return array_map(intval(...), $configured);
        }

        return app(AccessGatewayInterface::class)->adminUserIds();
    }
}
