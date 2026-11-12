<?php

namespace Modules\Auth\Http\Controllers\Admin\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Auth\Actions\Admin\User\RevokeUserSessionAction;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class RevokeUserSessionController extends Controller
{
    public function __construct(protected RevokeUserSessionAction $action) {}

    public function __invoke(string $id, string $tokenId, Request $request): Response
    {
        $this->action->handle($id, $tokenId);

        return response()->noContent(SymfonyResponse::HTTP_NO_CONTENT);
    }
}
