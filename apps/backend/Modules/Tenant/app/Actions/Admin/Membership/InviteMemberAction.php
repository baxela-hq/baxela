<?php

namespace Modules\Tenant\Actions\Admin\Membership;

use Modules\Core\Contracts\Gateways\Auth\AccessGatewayInterface;
use Modules\Tenant\Exceptions\Membership\AlreadyMemberException;
use Modules\Tenant\Exceptions\Membership\InviteFailedException;
use Modules\Tenant\Models\TenantMembership;
use Modules\Tenant\Schemas\TenantMembership\TenantMembershipSchema;
use Modules\Tenant\Support\SerializesOwnerSet;
use Throwable;

/**
 * Invites a staff member into the active store (§6.5 Category 2): the
 * account is ensured through the Auth gateway (an unknown address gets an
 * inert account the invitee claims through sign-up), and the membership
 * row starts `invited`. Cross-store identity is preserved — the membership
 * is the store's grant, never the account's (A3).
 */
class InviteMemberAction
{
    use SerializesOwnerSet;

    public function __construct(private readonly AccessGatewayInterface $access) {}

    /**
     * @return array<string, mixed> the member shape of MembershipResource
     */
    public function handle(string $email): array
    {
        $tenantId = $this->contextTenantId();

        try {
            $membership = TenantMembership::query()->firstOrCreate(
                [
                    TenantMembershipSchema::TENANT_ID => $tenantId,
                    TenantMembershipSchema::USER_ID => $this->access->ensureUserByEmail($email),
                ],
                [
                    TenantMembershipSchema::STATUS => TenantMembershipSchema::STATUS_INVITED,
                    TenantMembershipSchema::IS_OWNER => false,
                    TenantMembershipSchema::JOINED_AT => null,
                ],
            );
        } catch (Throwable $e) {
            report($e);

            throw new InviteFailedException(previous: $e);
        }

        if ($membership->wasRecentlyCreated === false
            && $membership->{TenantMembershipSchema::STATUS} !== TenantMembershipSchema::STATUS_SUSPENDED) {
            // An active/invited membership already exists; re-inviting is
            // not a no-op the caller can confuse with success.
            throw new AlreadyMemberException;
        }

        return [
            'id' => (int) $membership->getKey(),
            'user_id' => (int) $membership->{TenantMembershipSchema::USER_ID},
            'email' => $this->access->getUserEmailsByIds(
                [(int) $membership->{TenantMembershipSchema::USER_ID}],
            )[$membership->{TenantMembershipSchema::USER_ID}] ?? null,
            'status' => (string) $membership->{TenantMembershipSchema::STATUS},
            'is_owner' => (bool) $membership->{TenantMembershipSchema::IS_OWNER},
            'joined_at' => $membership->{TenantMembershipSchema::JOINED_AT}?->toIso8601String(),
        ];
    }
}
