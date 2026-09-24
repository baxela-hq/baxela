<?php

namespace Modules\Auth\Http\Controllers\User\Account;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Auth\Actions\User\Account\DestroySessionAction;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class DestroySessionController extends Controller
{
    public function __construct(protected DestroySessionAction $action) {}

    public function __invoke(string $id, Request $request): Response
    {
        $this->action->handle($request, $id);

        return response()->noContent(SymfonyResponse::HTTP_NO_CONTENT);
    }
}
