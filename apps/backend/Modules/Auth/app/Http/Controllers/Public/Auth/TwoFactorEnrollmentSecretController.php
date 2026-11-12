<?php

namespace Modules\Auth\Http\Controllers\Public\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Auth\Actions\Public\Auth\CompleteTwoFactorEnrollmentAction;

class TwoFactorEnrollmentSecretController extends Controller
{
    public function __construct(protected CompleteTwoFactorEnrollmentAction $action) {}

    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'challenge_token' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/'],
        ]);

        $result = $this->action->secret(
            (string) $request->input('challenge_token'),
        );

        // The secret + QR are one-time material — never cacheable.
        return response()->json([
            'data' => $result,
        ], 200, ['Cache-Control' => 'no-store']);
    }
}
