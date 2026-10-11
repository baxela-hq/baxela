<?php

namespace Modules\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Sentry\Laravel\Integration as SentryIntegration;
use Sentry\State\Scope;

class AssignRequestId
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        $requestId = (string) Str::uuid();

        Log::withContext([
            'request-id' => $requestId,
        ]);

        // Tag Sentry events with the same request ID returned in the
        // X-REQUEST-ID header, so a user report can be matched to both the
        // log lines and the error event. Request-scoped in practice: the
        // middleware overwrites the tag before anything reports, and no-ops
        // entirely when Sentry has no DSN configured.
        SentryIntegration::configureScope(function (Scope $scope) use ($requestId): void {
            $scope->setTag('request_id', $requestId);
        });

        $response = $next($request);
        $response->headers->set('X-REQUEST-ID', $requestId);

        return $response;

    }
}
