<?php

namespace Modules\Core\Support\Tenancy;

use Modules\Core\Exceptions\TenantContextMissingException;
use Modules\Core\Schemas\Shared\TenantColumn;

/**
 * Single sanctioned source of `tenant_id` for bulk/query-builder writes
 * (multi-tenancy §4.3, layer 0). Reads the active tenant from
 * `TenantContext` and throws outside MODE_TENANT — caller-supplied arrays
 * and request data must never carry `tenant_id`.
 */
final class TenantWriteStamp
{
    private function __construct() {}

    public static function tenantIdForWrite(): int
    {
        /** @var TenantContext $context */
        $context = app(TenantContext::class);

        if ($context->mode() !== TenantContext::MODE_TENANT) {
            throw new TenantContextMissingException;
        }

        return (int) $context->tenantId();
    }

    public static function column(): string
    {
        return TenantColumn::TENANT_ID;
    }
}
