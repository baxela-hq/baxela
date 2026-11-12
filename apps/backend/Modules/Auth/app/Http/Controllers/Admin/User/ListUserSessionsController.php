<?php

namespace Modules\Auth\Http\Controllers\Admin\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Auth\Actions\Admin\User\ListUserSessionsAction;
use Modules\Auth\Transformers\User\Account\SessionResource;

class ListUserSessionsController extends Controller
{
    public function __construct(protected ListUserSessionsAction $action) {}

    public function __invoke(string $id, Request $request): AnonymousResourceCollection
    {
        return SessionResource::collection($this->action->handle($id));
    }
}
