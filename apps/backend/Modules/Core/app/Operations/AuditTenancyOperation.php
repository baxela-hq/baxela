<?php

namespace Modules\Core\Operations;

use Modules\Core\Support\Tenancy\CentralOperation;

/**
 * Installation-wide ownership audit (multi-tenancy §6.5, Category 1).
 * A cross-store read run from the console through CentralOperations.
 */
final class AuditTenancyOperation implements CentralOperation
{
    public function id(): string
    {
        return 'core.tenancy.audit';
    }
}
