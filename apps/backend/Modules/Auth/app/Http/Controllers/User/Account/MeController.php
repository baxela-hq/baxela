<?php

namespace Modules\Auth\Http\Controllers\User\Account;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Auth\Actions\User\Account\MeAction;
use Modules\Auth\Transformers\Public\User\UserResource;

class MeController extends Controller
{
    public function __construct(protected MeAction $action) {}

    public function __invoke(Request $request): UserResource
    {
        return UserResource::make($this->action->handle($request));
    }
}
