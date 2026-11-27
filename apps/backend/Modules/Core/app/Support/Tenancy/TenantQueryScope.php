<?php

namespace Modules\Core\Support\Tenancy;

use Illuminate\Database\Query\Builder;
use Modules\Core\Exceptions\TenantContextMissingException;
use Modules\Core\Schemas\Shared\TenantColumn;

/**
 * Sanctioned tenant predicate for raw query-builder chains
 * (multi-tenancy §4.3, layer 0). A chain on a store-owned table either goes
 * through this guard, uses TenantWriteStamp for writes, or is wrapped in a
 * justified CentralOperations call. It fails closed outside MODE_TENANT.
 */
final class TenantQueryScope
{
    private function __construct() {}

    public static function apply(Builder $query): Builder
    {
        /** @var TenantContext $context */
        $context = app(TenantContext::class);
        if ($context->mode() !== TenantContext::MODE_TENANT) {
            throw new TenantContextMissingException;
        }

        return $query->where(TenantColumn::TENANT_ID, $context->tenantId());
    }
}
