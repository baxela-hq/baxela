<?php

namespace Modules\Core\Actions\Tenancy;

use Modules\Core\Operations\AuditTenancyOperation;
use Modules\Core\Support\Tenancy\TenancyAudit;

class AuditTenancyAction
{
    public function __construct(private readonly TenancyAudit $audit) {}

    /**
     * Runs inside CentralOperations::run().
     *
     * @return array{violations: list<array{type: string, table: string, detail: string}>, relations: int}
     */
    public function execute(AuditTenancyOperation $operation): array
    {
        return $this->audit->run();
    }
}
