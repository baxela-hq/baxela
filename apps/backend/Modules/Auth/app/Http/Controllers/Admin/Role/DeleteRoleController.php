<?php

namespace Modules\Auth\Http\Controllers\Admin\Role;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Auth\Actions\Admin\Role\DeleteRoleAction;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class DeleteRoleController extends Controller
{
    public function __construct(protected DeleteRoleAction $action) {}

    public function __invoke(string $id, Request $request): Response
    {
        $this->action->handle($id);

        return response()->noContent(SymfonyResponse::HTTP_NO_CONTENT);
    }
}
