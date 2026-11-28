<?php

namespace Modules\Core\Support\Tenancy;

use Illuminate\Contracts\Auth\Authenticatable;
use Modules\Core\Contracts\Gateways\Auth\AccessGatewayInterface;
use Modules\Core\Contracts\Gateways\Tenant\TenantGatewayInterface;
use Modules\Core\Exceptions\TenantNotFoundException;

/**
 * The authorization boundary (multi-tenancy §3.2) — invoked inside
 * `PermissionMiddleware::handle()` (single registration point, no
 * per-module route edits). Identification (§3.1) grants nothing; this is
 * where the resolved tenant is earned:
 *
 *  - the caller must hold an ACTIVE membership in the resolved store, or
 *    be the platform super-admin (F5's bypass, now team-context-immune);
 *  - a suspended store denies its members (§3.1) while the super-admin
 *    retains access, including reactivation;
 *  - membership is read per request — revocation takes effect on the very
 *    next request (§10 row 33), and nothing is cached beyond it.
 *
 * Returns the resolved tenant id so the permission check can run under the
 * matching spatie team (§5).
 */
final class AuthorizeTenantAccess
{
    private function __construct() {}

    /**
     * @return int the resolved tenant id for the team context (§5)
     *
     * @throws TenantNotFoundException 403 membership_required / suspended
     */
    public static function assertAdminAccess(Authenticatable $user): int
    {
        $context = app(TenantContext::class);

        if ($context->mode() !== TenantContext::MODE_TENANT || $context->tenantId() === null) {
            // Fail closed: an admin route without a resolved store is a
            // programming/deployment bug, never an implicit grant.
            throw TenantNotFoundException::noMembership();
        }

        $tenantId = (int) $context->tenantId();

        if (app(AccessGatewayInterface::class)->isPlatformSuperAdmin($user)) {
            // Full admin access on every store, suspended included —
            // reactivation is exactly this path (§3.1).
            return $tenantId;
        }

        if (! app(TenantGatewayInterface::class)->isMembershipActive((int) $user->getAuthIdentifier(), $tenantId)) {
            throw TenantNotFoundException::noMembership();
        }

        $tenant = $context->tenant();

        if ($tenant !== null && $tenant->suspended) {
            throw TenantNotFoundException::suspended($tenant);
        }

        return $tenantId;
    }
}
