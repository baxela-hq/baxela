<?php

namespace Modules\Audit\Actions\Admin\AuditLog;

use Illuminate\Database\Eloquent\Model;
use Modules\Audit\Schemas\AuditLog\AuditLogSchema;

class ShowAuditLogAction extends AbstractAuditLogAction
{
    public function handle(string $id): Model
    {
        return $this->model->query()
            ->with(AuditLogSchema::RES_USER)
            ->findOrFail($id);
    }
}
