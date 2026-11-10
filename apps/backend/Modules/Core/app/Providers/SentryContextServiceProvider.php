<?php

namespace Modules\Core\Providers;

use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;
use Sentry\Breadcrumb;
use Sentry\State\Scope;
use Throwable;

/**
 * Enriches Sentry captures with request context (H.2): the request ID
 * from AssignRequestId as a tag, the authenticated user, the route, and
 * a breadcrumb trail for the request lifecycle. Everything is DSN-gated —
 * when Sentry is inert (no DSN), these calls are silent no-ops on an
 * unconfigured client, so the overhead is a few function calls that go
 * nowhere. When the DSN is set, every captured exception arrives with
 * the context needed to reproduce it.
 */
class SentryContextServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Tag every capture with the request ID — this is the same ID
        // the AssignRequestId middleware puts into the response header,
        // so a Sentry issue and a support ticket correlate.
        $this->app->rebinding('request', function ($app, $request): void {
            $this->enrich($request);
        });

        if ($this->app->runningInConsole()) {
            return;
        }

        $this->app->booted(function (): void {
            $this->enrich($this->app['request']);
        });
    }

    protected function enrich(Request $request): void
    {
        try {
            \Sentry\configureScope(function (Scope $scope) use ($request): void {
                // Request ID (X-Request-ID from the middleware)
                $requestId = $request->attributes->get('request_id')
                    ?? $request->header('X-Request-ID');

                if (is_string($requestId) && $requestId !== '') {
                    $scope->setTag('request_id', $requestId);
                }

                // Route context
                $route = $request->route();
                if ($route !== null) {
                    $scope->setTag('route', $route->getName() ?? $route->uri());
                    $scope->setTag('method', $request->getMethod());
                }

                // Authenticated user (id only — never PII in Sentry)
                $userId = $request->user()?->id;
                if ($userId !== null) {
                    $scope->setUser(['id' => (string) $userId]);
                }

                // Breadcrumb: the request that led to whatever comes next
                \Sentry\addBreadcrumb(new Breadcrumb(
                    Breadcrumb::LEVEL_INFO,
                    Breadcrumb::TYPE_NAVIGATION,
                    'request',
                    $request->getMethod().' '.$request->getRequestUri(),
                ));
            });
        } catch (Throwable) {
            // Enrichment must never break the request — if Sentry's
            // client isn't configured, these calls throw internally.
        }
    }

    /**
     * Record a domain breadcrumb — call from actions/listeners that
     * want to leave a trail (checkout steps, payment transitions).
     * Inert when Sentry has no DSN.
     */
    public static function breadcrumb(string $category, string $message, array $metadata = []): void
    {
        try {
            \Sentry\addBreadcrumb(new Breadcrumb(
                Breadcrumb::LEVEL_INFO,
                Breadcrumb::TYPE_DEFAULT,
                $category,
                $message,
                $metadata,
            ));
        } catch (Throwable) {
            // No-op when Sentry is unconfigured.
        }
    }
}
