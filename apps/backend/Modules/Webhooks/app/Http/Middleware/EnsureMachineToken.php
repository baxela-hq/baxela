<?php

namespace Modules\Webhooks\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Machine-route guard (gap-C2 D14): requires a REAL bearer personal
 * access token — session cookies never authenticate here, wildcard
 * ('*') abilities are rejected, and the token must be unexpired with an
 * active owner. The ability check itself runs per-route via
 * auth:sanctum:ability or the controller-side check; this middleware is
 * the bearer-only precondition.
 */
class EnsureMachineToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $bearer = $request->bearerToken();

        if ($bearer === null || $request->hasSession() && $request->session()->getId() !== null && ! $request->bearerToken()) {
            return $this->reject();
        }

        $accessToken = PersonalAccessToken::findToken($bearer);

        if (is_null($accessToken)) {
            return $this->reject();
        }

        // Wildcard tokens are refused at mint time; refuse them here too
        // (defense in depth — gap-C2 D14).
        if (in_array('*', (array) $accessToken->abilities, true)) {
            return $this->reject();
        }

        if ($accessToken->expires_at !== null && $accessToken->expires_at->isPast()) {
            return $this->reject();
        }

        $owner = $accessToken->tokenable;

        if (is_null($owner) || ! ($owner->is_active ?? true)) {
            return $this->reject();
        }

        return $next($request);
    }

    private function reject(): Response
    {
        return response()->json([
            'message' => 'Machine routes require a scoped bearer token.',
        ], 401);
    }
}
