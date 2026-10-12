<?php

namespace Modules\Audit\Http\Controllers\Admin\AuditLog;

use App\Http\Controllers\Controller;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Audit\Actions\Admin\AuditLog\ListAuditLogsAction;
use Modules\Audit\Transformers\Admin\AuditLog\AuditLogResource;

class ListAuditLogsController extends Controller
{
    public function __construct(protected ListAuditLogsAction $action) {}

    public function __invoke(): AnonymousResourceCollection
    {
        return AuditLogResource::collection($this->action->handle());
    }
}
