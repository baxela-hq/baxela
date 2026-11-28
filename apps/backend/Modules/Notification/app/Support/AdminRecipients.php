<?php

namespace Modules\Notification\Support;

use Modules\Core\Contracts\Gateways\Auth\AccessGatewayInterface;
use Modules\Core\Support\Tenancy\TenantContext;

/**
 * Recipient ids for admin database notifications: only the active store's
 * active staff (multi-tenancy §6.12). The staff query is team-aware — it
 * follows the tenant context through the role pivot — and a configured
 * ADMIN_NOTIFICATION_USER_IDS list can only narrow that set, never widen
 * it to users outside the store.
 */
class AdminRecipients
{
    /**
     * @return array<int, int>
     */
    public static function databaseIds(): array
    {
        $tenantId = app(TenantContext::class)->tenantId();

        if ($tenantId === null) {
            // Platform/system alerts have no store to fan out to. Resolve
            // them against the installation's platform staff; an explicit
            // ADMIN_NOTIFICATION_USER_IDS list may narrow that set but must
            // not widen it to arbitrary user ids.
            $configured = array_values(array_map(
                intval(...),
                config('notification.notifications.admin_recipients.database', []),
            ));
            $staff = app(AccessGatewayInterface::class)->adminUserIds();

            return $configured === [] ? $staff : array_values(array_intersect($staff, $configured));
        }

        $staff = app(AccessGatewayInterface::class)->tenantAdminUserIds($tenantId);
        $configured = config('notification.notifications.admin_recipients.database', []);

        if (! empty($configured)) {
            return array_values(array_intersect($staff, array_map(intval(...), $configured)));
        }

        return $staff;
    }
}
