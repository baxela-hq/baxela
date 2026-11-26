<?php

namespace Modules\Tenant\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Models\Traits\TenantScopedTrait;
use Modules\Core\Schemas\Shared\TenantColumn;

/**
 * A store-scoped model on the platform tier (multi-tenancy §2): its
 * `tenant_id` is nullable, and a registered central operation may write a
 * platform row with the column left NULL.
 */
class PlatformTierProbe extends Model
{
    use TenantScopedTrait;

    protected $table = 'probe_platform_rows';

    public $timestamps = false;

    protected $fillable = [
        TenantColumn::TENANT_ID,
    ];

    public static function isPlatformTier(): bool
    {
        return true;
    }
}
