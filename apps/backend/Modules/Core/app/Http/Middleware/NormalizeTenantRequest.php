<?php

namespace Modules\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stage 1 of tenant identification (multi-tenancy §3.1) — a GLOBAL
 * middleware registered once in bootstrap/app.php. Global middleware runs
 * before the router matches a route, so nothing here may depend on the
 * route, the database, or the authenticated user: this stage only
 * normalizes the transport-level candidate signals and parks them as
 * typed request attributes for Stage 2 (ResolveTenant), which runs on the
 * matched route and decides.
 *
 * It decides nothing, and it normalizes every request: the `api` group
 * also carries routes that live outside the `api/` URI prefix.
 */
class NormalizeTenantRequest
{
    /** Request attribute keys Stage 2 reads. */
    public const string ATTR_TENANT_ID = 'tenant.candidate_id';

    public const string ATTR_TENANT_HOST = 'tenant.candidate_host';

    public const string ATTR_HOST = 'tenant.host';

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $request->attributes->set(self::ATTR_HOST, self::normalizeHost($request->getHost()));

        $id = $request->header('X-Tenant-Id');
        $id = is_string($id) ? trim($id) : '';
        $request->attributes->set(
            self::ATTR_TENANT_ID,
            preg_match('/^[1-9]\d{0,17}$/', $id) === 1 ? (int) $id : null,
        );

        // A missing header comes back as an empty string.
        $host = (string) $request->header('X-Tenant-Host');
        $request->attributes->set(
            self::ATTR_TENANT_HOST,
            $host !== '' ? self::normalizeHost($host) : null,
        );

        return $next($request);
    }

    /**
     * Lowercase, punycode, no port, no trailing dot; null for anything
     * that is not a usable DNS name (empty, an IP literal, or `*.local`
     * in production). IP literals are refused rather than resolved: no
     * store is ever addressed by IP.
     */
    public static function normalizeHost(?string $host): ?string
    {
        $host = strtolower(trim((string) $host));
        $host = rtrim($host, '.');
        $host = preg_replace('/:\d+$/', '', $host) ?? $host;

        if ($host === '' || filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return null;
        }

        $ascii = function_exists('idn_to_ascii') ? idn_to_ascii($host, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46) : false;
        $host = is_string($ascii) && $ascii !== '' ? strtolower($ascii) : $host;

        if (app()->environment('production') && str_ends_with($host, '.local')) {
            return null;
        }

        return $host;
    }
}
