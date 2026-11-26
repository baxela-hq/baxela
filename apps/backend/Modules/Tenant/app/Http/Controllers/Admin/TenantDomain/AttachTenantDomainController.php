<?php

namespace Modules\Tenant\Http\Controllers\Admin\TenantDomain;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Tenant\Actions\Admin\TenantDomain\AttachTenantDomainAction;
use Modules\Tenant\Http\Requests\Admin\TenantDomain\TenantDomainRequest;
use Modules\Tenant\Transformers\Admin\TenantDomain\TenantDomainResource;
use Symfony\Component\HttpFoundation\Response;

class AttachTenantDomainController extends Controller
{
    public function __construct(protected AttachTenantDomainAction $action) {}

    public function __invoke(string $id, TenantDomainRequest $request): JsonResponse
    {
        return (new TenantDomainResource($this->action->handle($id, $request->validated())))
            ->response()->setStatusCode(Response::HTTP_CREATED);
    }
}
