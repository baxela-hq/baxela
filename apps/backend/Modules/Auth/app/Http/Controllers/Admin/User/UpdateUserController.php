<?php

namespace Modules\Auth\Http\Controllers\Admin\User;

use App\Http\Controllers\Controller;
use Modules\Auth\Actions\Admin\User\UpdateUserAction;
use Modules\Auth\Http\Requests\Admin\User\UserRequest;
use Modules\Auth\Transformers\Admin\User\UserResource;

class UpdateUserController extends Controller
{
    public function __construct(protected UpdateUserAction $action) {}

    public function __invoke(string $id, UserRequest $request): UserResource
    {
        return UserResource::make($this->action->handle($id, $request));
    }
}
