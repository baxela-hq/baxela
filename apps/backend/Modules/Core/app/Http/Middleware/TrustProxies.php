<?php

namespace Modules\Core\Http\Middleware;

use Illuminate\Http\Request;

/**
 * Config-driven TrustProxies (S.3 D5): which IPs may send forwarding
 * headers, and which headers they may send.
 *
 * The stock middleware takes both from boot-time configuration and is
 * therefore untestable at runtime; reading them per request keeps the
 * trust boundary explicit and env-switchable per deployment.
 *
 * Security posture: `core.trusted_proxies` defaults to empty — trust
 * NOBODY, and a spoofed X-Forwarded-Proto can never flip
 * `$request->secure()`. Deployments set TRUSTED_PROXIES to the proxy
 * network's subnet (see the compose `proxy` network) — never `*`, which
 * on the shared compose network would extend trust to every container
 * peer. The trusted header set is deliberately minimal (D5/F2): the
 * auth rate limiter keys on $request->ip(), so X-Forwarded-For must
 * stay trusted; host/port/prefix forwarding is not needed by this
 * platform and stays off.
 */
class TrustProxies extends \Illuminate\Http\Middleware\TrustProxies
{
    /**
     * The trusted proxy IPs/CIDRs — comma-separated env value, read per
     * request. Empty string trusts nothing.
     *
     * @return array<int, string>|string|null
     */
    protected function proxies(): array|string|null
    {
        return static::$alwaysTrustProxies ?: config('core.trusted_proxies');
    }

    /**
     * The only forwarding headers honored: client IP + scheme.
     */
    protected function headers(): int
    {
        return static::$alwaysTrustHeaders ?: (
            Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PROTO
        );
    }
}
