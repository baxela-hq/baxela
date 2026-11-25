<?php

namespace Modules\Tenant\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Models\Traits\TenantScopedTrait;
use Modules\Tenant\Schemas\TenantLanguage\TenantLanguageSchema;

/**
 * A store's enabled language (multi-tenancy §4.1, P2). Store-owned: reads
 * are filtered and writes stamped by the tenant context — never read
 * unfiltered.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $language_id
 */
class TenantLanguage extends Model
{
    use TenantScopedTrait;

    protected $table = TenantLanguageSchema::TABLE;

    protected $fillable = [
        TenantLanguageSchema::LANGUAGE_ID,
    ];

    protected $casts = [
        TenantLanguageSchema::TENANT_ID => 'integer',
        TenantLanguageSchema::LANGUAGE_ID => 'integer',
    ];

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, TenantLanguageSchema::TENANT_ID);
    }
}
