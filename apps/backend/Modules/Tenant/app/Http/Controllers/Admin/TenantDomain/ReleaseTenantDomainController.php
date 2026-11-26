<?php

namespace Modules\Tenant\Http\Controllers\Admin\TenantDomain;

use App\Http\Controllers\Controller;
use Modules\Tenant\Actions\Admin\TenantDomain\ReleaseTenantDomainAction;
use Symfony\Component\HttpFoundation\Response;

class ReleaseTenantDomainController extends Controller
{
    public function __construct(protected ReleaseTenantDomainAction $action) {}

    public function __invoke(string $id, string $domainId): Response
    {
        $this->action->handle($id, $domainId);

        return response()->noContent(Response::HTTP_NO_CONTENT);
    }
}
