<?php

namespace Modules\Tenant\Support;

use Throwable;

/**
 * Tells WHICH unique constraint a violation hit. The violation message
 * is engine-specific and always contains the statement, so a loose
 * "does it mention slug" test matches every insert — it has to be the
 * constraint or the column:
 *
 *   PostgreSQL  … violates unique constraint "tenant_tenants_slug_unique"
 *   SQLite      UNIQUE constraint failed: tenant_tenants.slug
 */
final class UniqueViolation
{
    public static function on(Throwable $e, string $column): bool
    {
        $message = $e->getMessage();

        return str_contains($message, '_'.$column.'_unique"')
            || preg_match('/constraint failed:[^"]*\.'.$column.'\b/', $message) === 1;
    }
}
