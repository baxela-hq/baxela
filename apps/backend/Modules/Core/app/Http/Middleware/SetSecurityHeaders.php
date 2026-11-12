<?php

namespace Modules\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The two API security headers nginx does not set (S.3 D1–D3):
 * Content-Security-Policy and conditional Strict-Transport-Security.
 * nginx keeps owning the baseline (nosniff / X-Frame-Options /
 * Referrer-Policy) for static files, its own error pages, and Reverb —
 * this middleware must never duplicate those.
 *
 * Coverage (D6): the api group covers successes and matched-route
 * rendered exceptions; the exception render closure covers unmatched
 * /api/* JSON 404s via the same apply() helper. Both may process one
 * response — headers->set() replaces, so application is idempotent.
 */
class SetSecurityHeaders
{
    public const string CSP_HEADER = 'Content-Security-Policy';

    /** A JSON API serves data, not documents (D2). */
    public const string CSP_VALUE = 'default-src \'none\'; frame-ancestors \'none\'';

    public const string HSTS_HEADER = 'Strict-Transport-Security';

    public const string HSTS_VALUE = 'max-age=31536000; includeSubDomains';

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        return static::apply($response, $request);
    }

    /**
     * Attach the headers to any response type (JSON envelopes, binary
     * downloads). HSTS only when the request is genuinely secure per
     * the trusted-proxy configuration (D3) — never a raw forwarded-
     * header read here. Sets individual headers only; protocol headers
     * (Retry-After, Content-Disposition, …) are never touched.
     */
    public static function apply(Response $response, Request $request): Response
    {
        $response->headers->set(self::CSP_HEADER, self::CSP_VALUE);

        if ($request->secure()) {
            $response->headers->set(self::HSTS_HEADER, self::HSTS_VALUE);
        }

        return $response;
    }
}
