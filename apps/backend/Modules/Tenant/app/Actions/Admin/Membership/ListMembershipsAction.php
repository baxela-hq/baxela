<?php

namespace Modules\Tenant\Actions\Admin\Membership;

use Illuminate\Support\Collection;
use Modules\Core\Contracts\Gateways\Auth\AccessGatewayInterface;
use Modules\Tenant\Models\TenantMembership;
use Modules\Tenant\Schemas\TenantMembership\TenantMembershipSchema;
use Modules\Tenant\Support\SerializesOwnerSet;

/**
 * Lists the active store's members (multi-tenancy §6.5 Category 2, §10
 * row 104): the query is constrained to the context tenant, so a store
 * admin structurally cannot list another store's members.
 */
class ListMembershipsAction
{
    use SerializesOwnerSet;

    public function __construct(private readonly AccessGatewayInterface $access) {}

    /**
     * @return Collection<int, array{id: int, user_id: int, email: string|null, status: 'active'|'invited'|'suspended', is_owner: bool, joined_at: string|null}>
     */
    public function handle(): Collection
    {
        $tenantId = $this->contextTenantId();

        $memberships = TenantMembership::query()
            ->where(TenantMembershipSchema::TENANT_ID, $tenantId)
            ->orderBy(TenantMembershipSchema::ID)
            ->get();

        $emails = $this->access->getUserEmailsByIds(
            $memberships->pluck(TenantMembershipSchema::USER_ID)->all(),
        );

        return $memberships->map(fn (TenantMembership $membership): array => [
            'id' => (int) $membership->getKey(),
            'user_id' => (int) $membership->{TenantMembershipSchema::USER_ID},
            'email' => $emails[$membership->{TenantMembershipSchema::USER_ID}] ?? null,
            'status' => (string) $membership->{TenantMembershipSchema::STATUS},
            'is_owner' => (bool) $membership->{TenantMembershipSchema::IS_OWNER},
            'joined_at' => $membership->{TenantMembershipSchema::JOINED_AT}?->toIso8601String(),
        ]);
    }
}
