<?php

namespace Modules\Notification\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Broadcast;
use Modules\Core\Contracts\Gateways\Auth\AccessGatewayInterface;
use Modules\Core\Contracts\Gateways\Tenant\TenantGatewayInterface;
use Modules\Core\Support\Tenancy\TenantContext;
use Modules\Notification\Models\Notification;
use Modules\Notification\Schemas\Notification\NotificationSchema;

/**
 * Channel authorization rules for the realtime notification stream.
 *
 * Called from the module service provider at boot. The tenant segment is
 * part of every channel name (§9.7): the auth endpoint is platform-level,
 * so it must validate the store itself — a signed-in user may not reach a
 * different store's stream by editing the channel string.
 */
class BroadcastChannels
{
    public static function register(): void
    {
        Broadcast::channel(
            'tenant.{tenantId}.notification.admin.{id}',
            fn ($user, string|int $tenantId, string|int $id): bool => self::authorize($user, (int) $tenantId, (int) $id, 'admin'),
        );
        Broadcast::channel(
            'tenant.{tenantId}.notification.user.{id}',
            fn ($user, string|int $tenantId, string|int $id): bool => self::authorize($user, (int) $tenantId, (int) $id, 'user'),
        );
    }

    /**
     * Identity + store + audience check for one subscription.
     *
     * Staff are the active store's role holders under the team-aware
     * query; storefront subscribers are users with an active membership
     * or an existing customer notification in that store. A platform
     * role is not a store role, so it grants no stream here.
     */
    private static function authorize(?Authenticatable $user, int $tenantId, int $id, string $audience): bool
    {
        if ($user === null || (int) $user->getAuthIdentifier() !== $id) {
            return false;
        }

        $tenants = app(TenantGatewayInterface::class);
        $tenant = $tenants->find($tenantId);

        if ($tenant === null || $tenant->suspended) {
            return false;
        }

        return app(TenantContext::class)->runForTenant($tenantId, function () use ($tenantId, $id, $audience, $tenants): bool {
            $access = app(AccessGatewayInterface::class);

            if ($audience === 'admin') {
                return in_array($id, $access->tenantAdminUserIds($tenantId), true);
            }

            if ($tenants->isMembershipActive($id, $tenantId)) {
                return true;
            }

            return Notification::query()
                ->where(NotificationSchema::USER_ID, $id)
                ->where(NotificationSchema::AUDIENCE, 'user')
                ->exists();
        });
    }
}
