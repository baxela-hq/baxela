<?php

namespace Modules\Tenant\Http\Controllers\Admin\Tenant;

use App\Http\Controllers\Controller;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Tenant\Actions\Admin\Tenant\ListTenantsAction;
use Modules\Tenant\Transformers\Admin\Tenant\TenantResource;

class ListTenantsController extends Controller
{
    public function __construct(protected ListTenantsAction $action) {}

    public function __invoke(): AnonymousResourceCollection
    {
        return TenantResource::collection($this->action->handle());
    }
}
