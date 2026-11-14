<?php

namespace Modules\Auth\Http\Controllers\Public\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Auth\Exceptions\AuthException;
use Modules\Auth\Support\Google\SignInNonceService;

class RequestGoogleNonceAuthController extends Controller
{
    /**
     * Issues a single-use nonce for the Google button. POST (not GET) —
     * issuance mutates server state. Feature-flag gated exactly like the
     * credential endpoint so the public API surface stays consistent, and
     * rate-limited like it: an unthrottled issuer would let anyone fill
     * Redis with nonce entries.
     *
     * @throws AuthException when the feature is disabled or Redis is
     *                        unavailable (fails closed)
     */
    public function __invoke(SignInNonceService $nonces): JsonResponse
    {
        if (config('auth.google.enabled', false) === false) {
            throw AuthException::googleDisabled();
        }

        return response()->json([
            'data' => [
                'nonce' => $nonces->issue(),
            ],
        ]);
    }
}
