<?php

namespace Modules\Tenant\Http\Controllers\Admin\Tenant;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Tenant\Actions\Admin\Tenant\CreateTenantAction;
use Modules\Tenant\Http\Requests\Admin\Tenant\TenantRequest;
use Modules\Tenant\Transformers\Admin\Tenant\TenantResource;
use Symfony\Component\HttpFoundation\Response;

class CreateTenantController extends Controller
{
    public function __construct(protected CreateTenantAction $action) {}

    public function __invoke(TenantRequest $request): JsonResponse
    {
        return (new TenantResource($this->action->handle($request->validated())))
            ->response()->setStatusCode(Response::HTTP_CREATED);
    }
}
