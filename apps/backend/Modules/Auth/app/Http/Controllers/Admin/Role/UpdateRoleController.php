<?php

namespace Modules\Auth\Http\Controllers\Admin\Role;

use App\Http\Controllers\Controller;
use Modules\Auth\Actions\Admin\Role\UpdateRoleAction;
use Modules\Auth\Http\Requests\Admin\Role\RoleRequest;
use Modules\Auth\Transformers\Admin\Role\RoleResource;

class UpdateRoleController extends Controller
{
    public function __construct(protected UpdateRoleAction $action) {}

    public function __invoke(string $id, RoleRequest $request): RoleResource
    {
        return RoleResource::make($this->action->handle($id, $request));
    }
}
