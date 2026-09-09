<?php

namespace Modules\Auth\Http\Controllers\Admin\Role;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Auth\Actions\Admin\Role\CreateRoleAction;
use Modules\Auth\Http\Requests\Admin\Role\RoleRequest;
use Modules\Auth\Transformers\Admin\Role\RoleResource;

class CreateRoleController extends Controller
{
    public function __construct(protected CreateRoleAction $action) {}

    public function __invoke(RoleRequest $request): JsonResponse
    {
        return RoleResource::make($this->action->handle($request))
            ->response()
            ->setStatusCode(201);
    }
}
