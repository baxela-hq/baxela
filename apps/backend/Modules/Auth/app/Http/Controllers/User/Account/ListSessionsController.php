<?php

namespace Modules\Auth\Http\Controllers\User\Account;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Auth\Actions\User\Account\ListSessionsAction;
use Modules\Auth\Transformers\User\Account\SessionResource;

class ListSessionsController extends Controller
{
    public function __construct(protected ListSessionsAction $action) {}

    public function __invoke(Request $request): AnonymousResourceCollection
    {
        return SessionResource::collection($this->action->handle($request));
    }
}
