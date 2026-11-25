<?php

namespace Modules\Tenant\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Tenant\Schemas\TenantDomain\TenantDomainSchema;

/**
 * A store's domain and its verification lifecycle (§4.1, §9.7).
 * Metadata — NOT TenantScopedTrait; read through the gateway.
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $domain
 * @property TenantDomainSchema::STATUS_* $status
 * @property bool $is_primary
 * @property CarbonInterface|null $verified_at
 * @property string|null $verification_token_hash
 * @property TenantDomainSchema::METHOD_*|null $verification_method
 * @property CarbonInterface|null $verification_expires_at
 * @property CarbonInterface|null $released_at
 * @property CarbonInterface|null $cooling_off_until
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
class TenantDomain extends Model
{
    protected $table = TenantDomainSchema::TABLE;

    protected $fillable = [
        TenantDomainSchema::TENANT_ID,
        TenantDomainSchema::DOMAIN,
        TenantDomainSchema::STATUS,
        TenantDomainSchema::IS_PRIMARY,
        TenantDomainSchema::VERIFIED_AT,
        TenantDomainSchema::VERIFICATION_TOKEN_HASH,
        TenantDomainSchema::VERIFICATION_METHOD,
        TenantDomainSchema::VERIFICATION_EXPIRES_AT,
        TenantDomainSchema::RELEASED_AT,
        TenantDomainSchema::COOLING_OFF_UNTIL,
    ];

    protected $casts = [
        TenantDomainSchema::TENANT_ID => 'integer',
        TenantDomainSchema::IS_PRIMARY => 'boolean',
        TenantDomainSchema::VERIFIED_AT => 'datetime',
        TenantDomainSchema::VERIFICATION_EXPIRES_AT => 'datetime',
        TenantDomainSchema::RELEASED_AT => 'datetime',
        TenantDomainSchema::COOLING_OFF_UNTIL => 'datetime',
    ];

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, TenantDomainSchema::TENANT_ID);
    }

    /** Serves traffic: verified, and not inside a cooling-off window. */
    public function isServing(): bool
    {
        return $this->getAttribute(TenantDomainSchema::STATUS) === TenantDomainSchema::STATUS_VERIFIED;
    }
}
