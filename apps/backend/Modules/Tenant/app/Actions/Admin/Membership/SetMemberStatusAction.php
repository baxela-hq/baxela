<?php

namespace Modules\Tenant\Actions\Admin\Membership;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Modules\Tenant\Exceptions\Membership\LastOwnerException;
use Modules\Tenant\Exceptions\Membership\MembershipChangeFailedException;
use Modules\Tenant\Models\TenantMembership;
use Modules\Tenant\Schemas\TenantMembership\TenantMembershipSchema;
use Modules\Tenant\Support\SerializesOwnerSet;
use Throwable;

/**
 * Activates or suspends one member of the active store (§6.5 Category 2).
 * Suspending an owner can empty the owner set, so the store row is locked
 * and the last-owner invariant re-checked under the lock (rev 4.7, B6) —
 * the model guard keeps the write inside the context tenant.
 */
class SetMemberStatusAction
{
    use SerializesOwnerSet;

    public function handle(string $id, string $status): TenantMembership
    {
        $tenantId = $this->contextTenantId();

        try {
            return DB::transaction(function () use ($id, $status, $tenantId): TenantMembership {
                $this->lockTenantRow($tenantId);

                /** @var TenantMembership $membership */
                $membership = TenantMembership::query()
                    ->where(TenantMembershipSchema::TENANT_ID, $tenantId)
                    ->findOrFail($id);

                if ($status === TenantMembershipSchema::STATUS_SUSPENDED
                    && $membership->{TenantMembershipSchema::IS_OWNER}
                    && $membership->{TenantMembershipSchema::STATUS} === TenantMembershipSchema::STATUS_ACTIVE) {
                    $this->assertOtherActiveOwner($tenantId, (int) $membership->getKey());
                }

                $membership->update([TenantMembershipSchema::STATUS => $status]);

                return $membership;
            });
        } catch (LastOwnerException $e) {
            throw $e;
        } catch (ModelNotFoundException $e) {
            // A membership id that belongs to another store does not exist
            // in the active store — a plain 404, never a domain error.
            throw $e;
        } catch (Throwable $e) {
            report($e);

            throw new MembershipChangeFailedException(previous: $e);
        }
    }
}
