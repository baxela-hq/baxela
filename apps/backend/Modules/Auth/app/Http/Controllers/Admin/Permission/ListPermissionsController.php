<?php

namespace Modules\Auth\Http\Controllers\Admin\Permission;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Auth\Actions\Admin\Permission\ListPermissionsAction;
use Modules\Auth\Transformers\Admin\Permission\PermissionResource;

class ListPermissionsController extends Controller
{
    public function __construct(protected ListPermissionsAction $action) {}

    public function __invoke(Request $request): AnonymousResourceCollection
    {
        return PermissionResource::collection($this->action->handle());
    }
}
