<?php

namespace Modules\Auth\Http\Controllers\User\Account;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Auth\Actions\User\Account\SignOutAction;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class SignOutController extends Controller
{
    public function __construct(protected SignOutAction $action) {}

    public function __invoke(Request $request): Response
    {
        $this->action->handle($request);

        return response()->noContent(SymfonyResponse::HTTP_NO_CONTENT);
    }
}
