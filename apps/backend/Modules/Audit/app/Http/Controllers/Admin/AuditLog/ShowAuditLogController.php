<?php

namespace Modules\Audit\Http\Controllers\Admin\AuditLog;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Audit\Actions\Admin\AuditLog\ShowAuditLogAction;
use Modules\Audit\Transformers\Admin\AuditLog\AuditLogResource;

class ShowAuditLogController extends Controller
{
    public function __construct(protected ShowAuditLogAction $action) {}

    public function __invoke(string $id, Request $request): AuditLogResource
    {
        return AuditLogResource::make($this->action->handle($id));
    }
}
