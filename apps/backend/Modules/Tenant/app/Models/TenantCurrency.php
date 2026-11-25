<?php

namespace Modules\Tenant\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Models\Traits\TenantScopedTrait;
use Modules\Tenant\Schemas\TenantCurrency\TenantCurrencySchema;

/**
 * A store's enabled currency (multi-tenancy §4.1, P2). Store-owned: reads
 * are filtered and writes stamped by the tenant context — never read
 * unfiltered.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $currency_id
 */
class TenantCurrency extends Model
{
    use TenantScopedTrait;

    protected $table = TenantCurrencySchema::TABLE;

    protected $fillable = [
        TenantCurrencySchema::CURRENCY_ID,
    ];

    protected $casts = [
        TenantCurrencySchema::TENANT_ID => 'integer',
        TenantCurrencySchema::CURRENCY_ID => 'integer',
    ];

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, TenantCurrencySchema::TENANT_ID);
    }
}
