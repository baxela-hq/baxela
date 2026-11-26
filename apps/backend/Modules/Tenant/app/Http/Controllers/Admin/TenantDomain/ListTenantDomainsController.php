<?php

namespace Modules\Tenant\Http\Controllers\Admin\TenantDomain;

use App\Http\Controllers\Controller;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Tenant\Actions\Admin\TenantDomain\ListTenantDomainsAction;
use Modules\Tenant\Transformers\Admin\TenantDomain\TenantDomainResource;

class ListTenantDomainsController extends Controller
{
    public function __construct(protected ListTenantDomainsAction $action) {}

    public function __invoke(string $id): AnonymousResourceCollection
    {
        return TenantDomainResource::collection($this->action->handle($id));
    }
}
