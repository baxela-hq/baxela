<?php

namespace Modules\Tenant\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Tenant\Schemas\Tenant\TenantSchema;
use Modules\Tenant\Schemas\TenantDomain\TenantDomainSchema;
use Modules\Tenant\Schemas\TenantMembership\TenantMembershipSchema;

/**
 * A store (multi-tenancy §4.1). Metadata — deliberately NOT
 * TenantScopedTrait: the tenants table is what the scope filters *by*.
 * All reads go through TenantGatewayInterface.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string $email
 * @property TenantSchema::STATUS_* $status
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property CarbonInterface|null $deleted_at
 */
class Tenant extends Model
{
    use SoftDeletes;

    protected $table = TenantSchema::TABLE;

    protected $fillable = [
        TenantSchema::NAME,
        TenantSchema::SLUG,
        TenantSchema::EMAIL,
        TenantSchema::STATUS,
    ];

    protected $casts = [
        TenantSchema::STATUS => 'string',
    ];

    /** @return HasMany<TenantDomain, $this> */
    public function domains(): HasMany
    {
        return $this->hasMany(TenantDomain::class, TenantDomainSchema::TENANT_ID);
    }

    /** @return HasMany<TenantMembership, $this> */
    public function memberships(): HasMany
    {
        return $this->hasMany(TenantMembership::class, TenantMembershipSchema::TENANT_ID);
    }

    public function isSuspended(): bool
    {
        return $this->getAttribute(TenantSchema::STATUS) === TenantSchema::STATUS_SUSPENDED;
    }
}
