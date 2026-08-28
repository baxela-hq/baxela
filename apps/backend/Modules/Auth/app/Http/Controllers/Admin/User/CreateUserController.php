<?php

namespace Modules\Auth\Http\Controllers\Admin\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Auth\Actions\Admin\User\CreateUserAction;
use Modules\Auth\Http\Requests\Admin\User\UserRequest;
use Modules\Auth\Transformers\Admin\User\UserResource;

class CreateUserController extends Controller
{
    public function __construct(protected CreateUserAction $action) {}

    public function __invoke(UserRequest $request): JsonResponse
    {
        return UserResource::make($this->action->handle($request))
            ->response()
            ->setStatusCode(201);
    }
}
