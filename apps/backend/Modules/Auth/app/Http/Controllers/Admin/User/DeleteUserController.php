<?php

namespace Modules\Auth\Http\Controllers\Admin\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Auth\Actions\Admin\User\DeleteUserAction;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class DeleteUserController extends Controller
{
    public function __construct(protected DeleteUserAction $action) {}

    public function __invoke(string $id, Request $request): Response
    {
        $this->action->handle($id);

        return response()->noContent(SymfonyResponse::HTTP_NO_CONTENT);
    }
}
