<?php

namespace Modules\Tenant\Http\Controllers\Admin\Tenant;

use App\Http\Controllers\Controller;
use Modules\Tenant\Actions\Admin\Tenant\DeleteTenantAction;
use Symfony\Component\HttpFoundation\Response;

class DeleteTenantController extends Controller
{
    public function __construct(protected DeleteTenantAction $action) {}

    public function __invoke(string $id): Response
    {
        $this->action->handle($id);

        return response()->noContent(Response::HTTP_NO_CONTENT);
    }
}
