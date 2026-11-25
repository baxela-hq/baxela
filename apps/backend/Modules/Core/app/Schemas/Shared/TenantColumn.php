<?php

namespace Modules\Core\Schemas\Shared;

/**
 * The store-ownership column. Exported by Core as a plain class — not a
 * trait — because the scope and the model trait reference it statically
 * (`TenantColumn::TENANT_ID`), and PHP resolves trait constants only
 * through the class that uses them. No module ever imports the Tenant
 * module's schemas (schemas are module-internal).
 *
 * On pgsql `unsignedBigInteger` compiles to BIGINT (PostgreSQL has no
 * unsigned integer type); the PostgresUnsignedGrammar adds the
 * non-negative check.
 */
final class TenantColumn
{
    public const string TENANT_ID = 'tenant_id';

    private function __construct() {}
}
