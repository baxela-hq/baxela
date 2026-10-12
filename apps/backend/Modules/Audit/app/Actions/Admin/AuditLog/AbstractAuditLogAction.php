<?php

namespace Modules\Audit\Actions\Admin\AuditLog;

use Modules\Audit\Models\AuditLog;

abstract class AbstractAuditLogAction
{
    public function __construct(protected AuditLog $model) {}
}
