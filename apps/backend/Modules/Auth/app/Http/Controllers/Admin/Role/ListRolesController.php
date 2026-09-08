<?php

namespace Modules\Auth\Http\Controllers\Admin\Role;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Auth\Actions\Admin\Role\ListRolesAction;
use Modules\Auth\Transformers\Admin\Role\RoleResource;

class ListRolesController extends Controller
{
    public function __construct(protected ListRolesAction $action) {}

    public function __invoke(Request $request): AnonymousResourceCollection
    {
        return RoleResource::collection($this->action->handle());
    }
}
