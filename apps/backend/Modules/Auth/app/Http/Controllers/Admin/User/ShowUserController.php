<?php

namespace Modules\Auth\Http\Controllers\Admin\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Auth\Actions\Admin\User\ShowUserAction;
use Modules\Auth\Transformers\Admin\User\UserResource;

class ShowUserController extends Controller
{
    public function __construct(protected ShowUserAction $action) {}

    public function __invoke(string $id, Request $request): UserResource
    {
        return UserResource::make($this->action->handle($id));
    }
}
