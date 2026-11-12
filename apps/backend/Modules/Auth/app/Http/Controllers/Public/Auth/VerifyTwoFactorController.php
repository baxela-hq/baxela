<?php

namespace Modules\Auth\Http\Controllers\Public\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Auth\Actions\Public\Auth\VerifyTwoFactorAction;

class VerifyTwoFactorController extends Controller
{
    public function __construct(protected VerifyTwoFactorAction $action) {}

    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'challenge_token' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/'],
            'code' => ['required', 'string', 'min:6', 'max:16'],
        ]);

        $result = $this->action->handle(
            (string) $request->input('challenge_token'),
            (string) $request->input('code'),
        );

        return response()->json([
            'data' => [
                'user' => $result->user,
                'token' => $result->token,
            ],
        ]);
    }
}
