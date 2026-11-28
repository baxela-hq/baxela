<?php

namespace Modules\Tenant\Support;

use Modules\Core\Support\Tenancy\TenantContext;
use Modules\Tenant\Exceptions\Membership\LastOwnerException;
use Modules\Tenant\Models\Tenant;
use Modules\Tenant\Models\TenantMembership;
use Modules\Tenant\Schemas\TenantMembership\TenantMembershipSchema;

use function app;

/**
 * Owner-set serialization (multi-tenancy §6.5 Category 2, rev 4.7 B6):
 * every mutation capable of changing a store's active-owner set — removal,
 * suspension, demotion, transfer — locks the store's row
 * (`SELECT … FOR UPDATE`) BEFORE checking and changing, so two competing
 * transactions block and re-check instead of both passing a
 * read-committed check (the two-owners-both-deactivate race, §10 row 119).
 * SQLite takes no row lock; the two-connection proof is PostgreSQL-only.
 */
trait SerializesOwnerSet
{
    /** The store row, locked for the rest of the transaction. */
    protected function lockTenantRow(int $tenantId): Tenant
    {
        /** @var Tenant $tenant */
        $tenant = Tenant::query()->lockForUpdate()->findOrFail($tenantId);

        return $tenant;
    }

    /**
     * Active owners excluding the given membership — the count the
     * last-owner invariant checks under the held lock.
     */
    protected function activeOwnersExcluding(int $tenantId, ?int $membershipId = null): int
    {
        return TenantMembership::query()
            ->where(TenantMembershipSchema::TENANT_ID, $tenantId)
            ->where(TenantMembershipSchema::IS_OWNER, true)
            ->where(TenantMembershipSchema::STATUS, TenantMembershipSchema::STATUS_ACTIVE)
            ->when($membershipId !== null, fn ($query) => $query->whereNot(TenantMembershipSchema::ID, $membershipId))
            ->count();
    }

    /**
     * Rejects removing/suspending/demoting the only active owner.
     */
    protected function assertOtherActiveOwner(int $tenantId, ?int $membershipId = null): void
    {
        if ($this->activeOwnersExcluding($tenantId, $membershipId) === 0) {
            throw new LastOwnerException;
        }
    }

    /** The active context tenant id (Category 2 runs in MODE_TENANT). */
    protected function contextTenantId(): int
    {
        $tenantId = app(TenantContext::class)->tenantId();

        if ($tenantId === null) {
            throw new \LogicException('Membership operations require a resolved tenant context.');
        }

        return $tenantId;
    }
}
