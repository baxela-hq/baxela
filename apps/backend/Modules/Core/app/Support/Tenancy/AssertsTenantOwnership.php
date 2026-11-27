<?php

namespace Modules\Core\Support\Tenancy;

use Modules\Core\Exceptions\TenantOwnershipException;

/**
 * Batch ownership guard for writes that take cross-entity ids outside a
 * FormRequest (multi-tenancy §4.3, layer 2). Validation runs before any
 * pivot sync or bulk insertion and inside the same transaction as the
 * write. Throws on the first id not owned by the active tenant.
 */
final class AssertsTenantOwnership
{
    private function __construct() {}

    /**
     * @param  array<int, int|string|null>  $ids
     */
    public static function assertIds(array $ids, callable $exists): void
    {
        foreach (array_unique(array_filter($ids, static fn ($value) => $value !== null)) as $id) {
            if (! $exists((int) $id)) {
                throw new TenantOwnershipException;
            }
        }
    }
}
