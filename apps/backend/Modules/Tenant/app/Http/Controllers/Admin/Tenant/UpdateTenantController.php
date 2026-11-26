<?php

namespace Modules\Tenant\Http\Controllers\Admin\Tenant;

use App\Http\Controllers\Controller;
use Modules\Tenant\Actions\Admin\Tenant\UpdateTenantAction;
use Modules\Tenant\Http\Requests\Admin\Tenant\TenantRequest;
use Modules\Tenant\Transformers\Admin\Tenant\TenantResource;

class UpdateTenantController extends Controller
{
    public function __construct(protected UpdateTenantAction $action) {}

    public function __invoke(string $id, TenantRequest $request): TenantResource
    {
        return TenantResource::make($this->action->handle($id, $request->validated()));
    }
}
