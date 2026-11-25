<?php

namespace Modules\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Core\Contracts\Gateways\Tenant\DTOs\TenantSnapshotDto;
use Modules\Core\Contracts\Gateways\Tenant\TenantGatewayInterface;
use Modules\Core\Exceptions\TenantNotFoundException;
use Modules\Core\Support\Tenancy\TenantContext;
use Modules\Core\Support\Tenancy\TenantResolved;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stage 2 of tenant identification (multi-tenancy §3.1) — the first
 * middleware of the `api` group. Group middleware runs on the MATCHED
 * route, so the route's name (hence its audience) is available here; its
 * position is fixed by the middleware priority list in bootstrap/app.php,
 * ahead of credential application, route-model binding, and throttling.
 *
 * This is identification, never authorization: it may read the principal
 * (membership selection, machine tokens) but grants nothing — §3.2 is the
 * authorization boundary.
 */
class ResolveTenant
{
    public const string AUDIENCE_ADMIN = 'admin';

    public const string AUDIENCE_USER = 'user';

    public const string AUDIENCE_PUBLIC = 'public';

    public const string AUDIENCE_WEBHOOK = 'webhook';

    public const string AUDIENCE_MACHINE = 'machine';

    /** Tenant-neutral (§3.1, B1): no resolution, context stays INACTIVE. */
    public const string AUDIENCE_ONBOARDING = 'onboarding';

    public const array AUDIENCES = [
        self::AUDIENCE_ADMIN,
        self::AUDIENCE_USER,
        self::AUDIENCE_PUBLIC,
        self::AUDIENCE_WEBHOOK,
        self::AUDIENCE_MACHINE,
        self::AUDIENCE_ONBOARDING,
    ];

    public function __construct(
        private readonly TenantGatewayInterface $gateway,
        private readonly ResolvesMachineTenant $machineTenant,
    ) {}

    /**
     * @throws TenantNotFoundException
     */
    public function handle(Request $request, Closure $next): Response
    {
        $route = $request->route();
        $name = (string) $route?->getName();

        $audience = $this->audienceOf($name);

        if ($audience === self::AUDIENCE_ONBOARDING) {
            // Tenant-neutral: self-service provisioning creates the store,
            // so there is nothing to resolve yet. The context must stay
            // INACTIVE; a previous request's residue is reset here too.
            app(TenantContext::class)->reset();

            return $next($request);
        }

        $tenant = $this->resolve($audience, $request, $route?->parameters() ?? []);

        app(TenantContext::class)->forTenant($tenant->id);

        // Store-dependent consumers (the per-request runtime, logging,
        // Sentry tagging) listen for this — Core raises it, modules react.
        event(new TenantResolved($tenant, $audience));

        return $next($request);
    }

    /**
     * The single audience definition (§3.1, F5): the THIRD segment of
     * `api.{module}.{audience}.{…}`. Anything else is unclassified and
     * fails closed — an API route without a classified audience must never
     * be trusted with tenant signals.
     *
     * @throws TenantNotFoundException
     */
    public static function audienceOf(string $name): string
    {
        $segments = explode('.', $name);

        $audience = $segments[2] ?? '';

        if (in_array($audience, self::AUDIENCES, true)) {
            return $audience;
        }

        // Declared platform routes carry no audience segment (or an empty
        // one). They identify like `public` — a host picks the storefront,
        // no header or membership is trusted beyond that.
        if (in_array(rtrim($name, '.'), (array) config('core.tenancy.platform_exceptions', []), true)) {
            return self::AUDIENCE_PUBLIC;
        }

        // An unnamed API route: identification still has to work for the
        // signed-in-user lookup (`/api/user`), so it is treated as public
        // too — its own auth middleware remains the gate.
        if ($name === '') {
            return self::AUDIENCE_PUBLIC;
        }

        throw TenantNotFoundException::unresolved('unclassified_route', ['route' => $name]);
    }

    /**
     * @param  array<string, mixed>  $parameters
     *
     * @throws TenantNotFoundException
     */
    private function resolve(string $audience, Request $request, array $parameters): TenantSnapshotDto
    {
        // The webhook audience's `{tenant-slug}` route parameter is
        // authoritative (§3.1, B2) — it outranks every header.
        if ($audience === self::AUDIENCE_WEBHOOK && isset($parameters['tenant-slug'])) {
            return $this->requireServable(
                $this->gateway->findBySlug((string) $parameters['tenant-slug']),
                'unknown_webhook_slug',
            );
        }

        if ($audience === self::AUDIENCE_MACHINE) {
            // From the bearer token alone — never a header.
            return $this->requireServable($this->machineTenant->resolve($request), 'invalid_machine_token');
        }

        $host = $request->attributes->get(NormalizeTenantRequest::ATTR_HOST);

        if ($audience === self::AUDIENCE_ADMIN) {
            $id = $request->attributes->get(NormalizeTenantRequest::ATTR_TENANT_ID);

            // Priority 1: X-Tenant-Id (admin only). The header names a
            // store; whether the caller may enter it is §3.2's decision.
            if ($id !== null) {
                return $this->requireServable($this->gateway->find((int) $id), 'unknown_tenant_id');
            }

            if ($this->isCentralHost($host)) {
                return $this->selectByMembership($request);
            }
        } elseif ($audience === self::AUDIENCE_PUBLIC || $audience === self::AUDIENCE_USER) {
            // Priority 2: X-Tenant-Host (public/user only) — the
            // storefront-forwarded host (§7). Never honored on admin routes.
            $tenantHost = $request->attributes->get(NormalizeTenantRequest::ATTR_TENANT_HOST);

            if (is_string($tenantHost) && $tenantHost !== '') {
                $resolved = $this->gateway->resolveByDomain($tenantHost);

                if ($resolved !== null) {
                    return $this->requireServable($resolved, 'unknown_tenant_host');
                }
            }
        }

        // Priority 3: the Host itself (API served on a tenant domain).
        if (is_string($host) && $host !== '') {
            $resolved = $this->gateway->resolveByDomain($host);

            if ($resolved !== null) {
                return $resolved;
            }
        }

        // Priority 4: the fallback list (dev/legacy hosts only).
        if (is_string($host) && $this->isFallbackHost($host)) {
            $resolved = $this->gateway->defaultTenant();

            if ($resolved !== null) {
                return $resolved;
            }
        }

        return $this->selectByMembership($request, true);
    }

    /**
     * Admin default selection (§3.1, A5): on a central host with no
     * X-Tenant-Id, exactly one active membership selects it; several need
     * the header; none is refused.
     *
     * @throws TenantNotFoundException
     */
    private function selectByMembership(Request $request, bool $lastResort = false): TenantSnapshotDto
    {
        $userId = $this->authenticatedUserId($request);

        if ($userId === null) {
            if ($lastResort) {
                // Nothing identified the caller: the host is unknown and
                // outside the fallback list.
                throw TenantNotFoundException::unresolved('unknown_host', ['host' => $request->attributes->get(NormalizeTenantRequest::ATTR_HOST) ?? '']);
            }

            throw TenantNotFoundException::noMembership();
        }

        $memberships = $this->gateway->membershipsFor($userId);

        if (count($memberships) === 1) {
            return $this->gateway->find($memberships[0]['id']) ?? throw TenantNotFoundException::unresolved('membership_tenant_missing');
        }

        if ($lastResort) {
            throw TenantNotFoundException::unresolved('unknown_host', ['host' => $request->attributes->get(NormalizeTenantRequest::ATTR_HOST) ?? '']);
        }

        return throw TenantNotFoundException::selectionRequired(array_column($memberships, 'slug'));
    }

    private function authenticatedUserId(Request $request): ?int
    {
        // Identification may read the principal; it grants nothing. The
        // guard is idempotent — resolving it early consumes no token.
        $user = Auth::guard('sanctum')->user();

        return $user === null ? null : (int) $user->getAuthIdentifier();
    }

    private function isCentralHost(?string $host): bool
    {
        $central = array_filter(array_map('trim', explode(',', (string) config('core.tenancy.central_hosts', ''))));

        return is_string($host) && in_array($host, $central, true);
    }

    private function isFallbackHost(?string $host): bool
    {
        $fallback = array_filter(array_map('trim', explode(',', (string) config('core.tenancy.fallback_hosts', ''))));

        return is_string($host) && in_array($host, $fallback, true);
    }

    /**
     * A suspended store resolves — identification succeeded — but may not
     * be served. The platform super-admin keeps admin access and may
     * reactivate (§3.1); everyone else is refused.
     *
     * @throws TenantNotFoundException
     */
    private function requireServable(?TenantSnapshotDto $tenant, string $reason): TenantSnapshotDto
    {
        if ($tenant === null) {
            throw TenantNotFoundException::unresolved($reason);
        }

        if (! $tenant->suspended) {
            return $tenant;
        }

        throw TenantNotFoundException::suspended($tenant);
    }
}
