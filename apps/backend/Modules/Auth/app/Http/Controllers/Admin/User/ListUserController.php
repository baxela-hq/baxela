<?php

namespace Modules\Auth\Http\Controllers\Admin\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Auth\Actions\Admin\User\ListUserAction;
use Modules\Auth\Transformers\Admin\User\UserResource;

class ListUserController extends Controller
{
    public function __construct(protected ListUserAction $action) {}

    public function __invoke(Request $request): AnonymousResourceCollection
    {
        return UserResource::collection($this->action->handle($request));
    }
}
