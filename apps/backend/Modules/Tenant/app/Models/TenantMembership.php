<?php

namespace Modules\Tenant\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Exceptions\TenantOwnershipException;
use Modules\Core\Support\Tenancy\TenantContext;
use Modules\Tenant\Schemas\TenantMembership\TenantMembershipSchema;

/**
 * A user's membership in a store (§4.1). Membership answers *belongs to*;
 * spatie answers *may do* — authorization is never derived from this row.
 *
 * Metadata for reads (gateway-only, so tenant selection can list a user's
 * memberships across stores), but every WRITE is tenant-confined: the
 * model guard rejects a row whose tenant differs from the context
 * (§6.5 Category 2).
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $user_id
 * @property TenantMembershipSchema::STATUS_* $status
 * @property bool $is_owner
 * @property CarbonInterface|null $joined_at
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
class TenantMembership extends Model
{
    protected $table = TenantMembershipSchema::TABLE;

    protected $fillable = [
        TenantMembershipSchema::TENANT_ID,
        TenantMembershipSchema::USER_ID,
        TenantMembershipSchema::STATUS,
        TenantMembershipSchema::IS_OWNER,
        TenantMembershipSchema::JOINED_AT,
    ];

    protected $casts = [
        TenantMembershipSchema::TENANT_ID => 'integer',
        TenantMembershipSchema::USER_ID => 'integer',
        TenantMembershipSchema::IS_OWNER => 'boolean',
        TenantMembershipSchema::JOINED_AT => 'datetime',
    ];

    protected static function booted(): void
    {
        // Writes are tenant-confined (§6.5 Category 2): outside MODE_TENANT
        // nothing may write a membership, and inside it the row must
        // belong to the context tenant. Provisioning (Category 3) runs
        // inside runForTenant($newTenant) and so satisfies this too.
        //
        // The model carries no store scope on purpose — reads go through
        // the gateway, which must list a user's memberships across stores
        // — so the creating hook stamps the context tenant itself, like
        // TenantScopedTrait does.
        static::creating(function (self $membership): void {
            if ($membership->getAttribute(TenantMembershipSchema::TENANT_ID) === null) {
                $membership->setAttribute(
                    TenantMembershipSchema::TENANT_ID,
                    app(TenantContext::class)->tenantId(),
                );
            }

            $membership->assertWithinContextTenant();
        });

        static::updating(function (self $membership): void {
            $membership->assertWithinContextTenant();
        });

        static::deleting(function (self $membership): void {
            $membership->assertWithinContextTenant();
        });
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, TenantMembershipSchema::TENANT_ID);
    }

    public function isActive(): bool
    {
        return $this->getAttribute(TenantMembershipSchema::STATUS) === TenantMembershipSchema::STATUS_ACTIVE;
    }

    private function assertWithinContextTenant(): void
    {
        $context = app(TenantContext::class);

        if ($context->mode() !== TenantContext::MODE_TENANT
            || $context->tenantId() !== (int) $this->getAttribute(TenantMembershipSchema::TENANT_ID)) {
            throw new TenantOwnershipException($this, (int) $this->getAttribute(TenantMembershipSchema::TENANT_ID), $context->tenantId());
        }
    }
}
