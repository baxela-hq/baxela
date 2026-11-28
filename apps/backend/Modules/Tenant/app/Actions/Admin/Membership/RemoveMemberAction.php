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
 * Removes one member from the active store (§6.5 Category 2). Removing an
 * owner can empty the owner set, so the store row is locked and the
 * last-owner invariant re-checked under the lock (rev 4.7, B6). Revocation
 * takes effect on the caller's very next request — the authorization
 * gateway reads memberships per request, nothing cached (§10 row 33).
 */
class RemoveMemberAction
{
    use SerializesOwnerSet;

    public function handle(string $id): void
    {
        $tenantId = $this->contextTenantId();

        try {
            DB::transaction(function () use ($id, $tenantId): void {
                $this->lockTenantRow($tenantId);

                /** @var TenantMembership $membership */
                $membership = TenantMembership::query()
                    ->where(TenantMembershipSchema::TENANT_ID, $tenantId)
                    ->findOrFail($id);

                if ($membership->{TenantMembershipSchema::IS_OWNER}
                    && $membership->{TenantMembershipSchema::STATUS} === TenantMembershipSchema::STATUS_ACTIVE) {
                    $this->assertOtherActiveOwner($tenantId, (int) $membership->getKey());
                }

                $membership->delete();
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
