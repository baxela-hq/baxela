<?php

namespace Modules\Tenant\Actions\Admin\Membership;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\Gateways\Auth\AccessGatewayInterface;
use Modules\Tenant\Exceptions\Membership\MembershipChangeFailedException;
use Modules\Tenant\Exceptions\Membership\OwnershipRequiredException;
use Modules\Tenant\Models\TenantMembership;
use Modules\Tenant\Schemas\TenantMembership\TenantMembershipSchema;
use Modules\Tenant\Support\SerializesOwnerSet;
use Throwable;

/**
 * Hands a store's ownership to one of its active members (§6.5 Category
 * 2, F4 owner invariant): grants and revokes in ONE transaction — the
 * target gains `is_owner` and the previous owner loses it atomically, so
 * the store always retains exactly one active owner. Requires the actor
 * to be the store's owner or the platform super-admin, and the store row
 * is locked across the check-and-change (rev 4.7, B6).
 */
class TransferOwnershipAction
{
    use SerializesOwnerSet;

    public function __construct(private readonly AccessGatewayInterface $access) {}

    public function handle(string $id): TenantMembership
    {
        $tenantId = $this->contextTenantId();
        $actorId = (int) Auth::id();

        try {
            return DB::transaction(function () use ($id, $tenantId, $actorId): TenantMembership {
                $this->lockTenantRow($tenantId);

                if (! $this->access->isPlatformSuperAdmin(Auth::user())) {
                    $actor = TenantMembership::query()
                        ->where(TenantMembershipSchema::TENANT_ID, $tenantId)
                        ->where(TenantMembershipSchema::USER_ID, $actorId)
                        ->first([TenantMembershipSchema::ID, TenantMembershipSchema::IS_OWNER]);

                    if ($actor === null || ! $actor->{TenantMembershipSchema::IS_OWNER}) {
                        throw new OwnershipRequiredException;
                    }
                }

                /** @var TenantMembership $target */
                $target = TenantMembership::query()
                    ->where(TenantMembershipSchema::TENANT_ID, $tenantId)
                    ->findOrFail($id);

                if ($target->{TenantMembershipSchema::STATUS} !== TenantMembershipSchema::STATUS_ACTIVE) {
                    throw new OwnershipRequiredException(meta: ['reason' => 'target_not_active']);
                }

                // Grant and revoke in the same transaction: the store never
                // has zero owners at any committed instant (F4).
                TenantMembership::query()
                    ->where(TenantMembershipSchema::TENANT_ID, $tenantId)
                    ->whereNot(TenantMembershipSchema::ID, $target->getKey())
                    ->update([TenantMembershipSchema::IS_OWNER => false]);

                $target->update([TenantMembershipSchema::IS_OWNER => true]);

                return $target->refresh();
            });
        } catch (OwnershipRequiredException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);

            throw new MembershipChangeFailedException(previous: $e);
        }
    }
}
