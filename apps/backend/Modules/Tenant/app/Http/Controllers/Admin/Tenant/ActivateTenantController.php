<?php

namespace Modules\Tenant\Http\Controllers\Admin\Tenant;

use App\Http\Controllers\Controller;
use Modules\Tenant\Actions\Admin\Tenant\SetTenantStatusAction;
use Modules\Tenant\Schemas\Tenant\TenantSchema;
use Modules\Tenant\Transformers\Admin\Tenant\TenantResource;

class ActivateTenantController extends Controller
{
    public function __construct(protected SetTenantStatusAction $action) {}

    public function __invoke(string $id): TenantResource
    {
        return TenantResource::make($this->action->handle($id, TenantSchema::STATUS_ACTIVE));
    }
}
