<?php

namespace Modules\Tenant\Actions\Admin\Membership;

use Modules\Core\Contracts\Gateways\Auth\AccessGatewayInterface;
use Modules\Tenant\Exceptions\Membership\RoleSyncFailedException;
use Modules\Tenant\Models\TenantMembership;
use Modules\Tenant\Schemas\TenantMembership\TenantMembershipSchema;
use Modules\Tenant\Support\SerializesOwnerSet;
use Throwable;

/**
 * Replaces one member's role set within the active store (§6.5 Category
 * 2, role assignment): the spatie team is the context tenant, so the sync
 * touches only this store's pivot rows (§5). Platform and foreign-team
 * roles are rejected by the gateway.
 */
class SyncMemberRolesAction
{
    use SerializesOwnerSet;

    public function __construct(private readonly AccessGatewayInterface $access) {}

    /**
     * @param  array<int, int>  $roleIds
     */
    public function handle(string $id, array $roleIds): void
    {
        $tenantId = $this->contextTenantId();

        try {
            $membership = TenantMembership::query()
                ->where(TenantMembershipSchema::TENANT_ID, $tenantId)
                ->findOrFail($id);

            $this->access->syncTenantMemberRoles(
                (int) $membership->{TenantMembershipSchema::USER_ID},
                $tenantId,
                array_map(intval(...), $roleIds),
            );
        } catch (Throwable $e) {
            report($e);

            throw new RoleSyncFailedException(previous: $e);
        }
    }
}
