<?php

namespace Modules\Tenant\Http\Controllers\Admin\Tenant;

use App\Http\Controllers\Controller;
use Modules\Tenant\Actions\Admin\Tenant\ShowTenantAction;
use Modules\Tenant\Transformers\Admin\Tenant\TenantResource;

class ShowTenantController extends Controller
{
    public function __construct(protected ShowTenantAction $action) {}

    public function __invoke(string $id): TenantResource
    {
        return TenantResource::make($this->action->handle($id));
    }
}
