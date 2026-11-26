<?php

namespace Modules\Tenant\Http\Controllers\User\Onboarding;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Tenant\Actions\User\Onboarding\ProvisionTenantAction;
use Modules\Tenant\Http\Requests\User\Onboarding\ProvisionTenantRequest;
use Modules\Tenant\Transformers\Admin\Tenant\TenantResource;
use Symfony\Component\HttpFoundation\Response;

class ProvisionTenantController extends Controller
{
    public function __construct(protected ProvisionTenantAction $action) {}

    public function __invoke(ProvisionTenantRequest $request): JsonResponse
    {
        $tenant = $this->action->handle($request->user(), $request->validated());

        return (new TenantResource($tenant))->response()->setStatusCode(Response::HTTP_CREATED);
    }
}
