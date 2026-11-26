<?php

namespace Modules\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Modules\Core\Contracts\Gateways\Tenant\DTOs\TenantSnapshotDto;
use Modules\Core\Contracts\Gateways\Tenant\TenantGatewayInterface;
use Modules\Core\Exceptions\TenantNotFoundException;
use Modules\Core\Support\Tenancy\TenantContext;
use Modules\Core\Support\Tenancy\TenantResolved;
use Sentry\Laravel\Integration as SentryIntegration;
use Sentry\State\Scope;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stage 2 of tenant identification (multi-tenancy §3.1) — the first
 * middleware of the `api` group. Group middleware runs on the MATCHED
 * route, so the route's name (hence its audience) is available here; its
 * position is fixed by the middleware priority list in bootstrap/app.php,
 * ahead of credential application, authentication, throttling and
 * route-model binding.
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

    /** Tenant-neutral (§3.1): no resolution, context stays INACTIVE. */
    public const string AUDIENCE_ONBOARDING = 'onboarding';

    /** Not a route audience: a declared platform-level route, tenant-neutral like onboarding. */
    public const string PLATFORM = 'platform';

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
        private readonly TenantContext $context,
    ) {}

    /**
     * @throws TenantNotFoundException
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Every request starts INACTIVE, whatever the previous one left —
        // including when this middleware is about to refuse the request.
        // php-fpm boots one request per process, but the invariant must
        // hold wherever the process survives: queue workers and tests.
        $this->context->reset();

        $route = $request->route();
        $name = (string) $route?->getName();
        $audience = self::audienceOf($name);

        if ($audience === self::AUDIENCE_ONBOARDING) {
            // The tenant-neutral audience is allowed on registered routes
            // only; anything else carrying it fails closed, so it can never
            // become a general bypass of tenant resolution.
            if (! in_array($name, (array) config('core.tenancy.onboarding_routes', []), true)) {
                throw TenantNotFoundException::unresolved('unregistered_onboarding_route', ['route' => $name]);
            }
        }

        if ($audience === self::AUDIENCE_ONBOARDING || $audience === self::PLATFORM) {
            // Tenant-neutral: self-service provisioning creates the store,
            // and platform routes serve the installation rather than a
            // store. The context stays INACTIVE.
            return $next($request);
        }

        $tenant = $this->resolve($audience, $request, $route?->parameters() ?? []);

        if ($tenant === null) {
            // No store can be selected for an unauthenticated caller; their
            // own auth middleware answers the request. The context is
            // INACTIVE, as it has been since this request began.
            return $next($request);
        }

        $this->context->forTenant($tenant->id);

        // Same pattern as AssignRequestId: every log line and Sentry event
        // of this request carries the store it served (§9.7 logging).
        Log::withContext([
            'tenant-id' => $tenant->id,
            'tenant-slug' => $tenant->slug,
        ]);

        SentryIntegration::configureScope(function (Scope $scope) use ($tenant): void {
            $scope->setTag('tenant_id', (string) $tenant->id);
        });

        // Store-dependent consumers listen for this — Core raises it, modules react.
        event(new TenantResolved($tenant, $audience));

        return $next($request);
    }

    /**
     * The single audience definition (§3.1): the THIRD segment of
     * `api.{module}.{audience}.{…}`. Anything else is unclassified and
     * fails closed — an API route without a classified audience must never
     * be trusted with tenant signals. Declared platform-level routes are the
     * one exception and are tenant-neutral.
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

        if (in_array(rtrim($name, '.'), self::platformRoutes(), true)) {
            return self::PLATFORM;
        }

        throw TenantNotFoundException::unresolved('unclassified_route', [
            'route' => $name === '' ? '(unnamed)' : $name,
        ]);
    }

    /**
     * @return list<string>
     */
    private static function platformRoutes(): array
    {
        $routes = (array) config('core.tenancy.platform_routes', []);

        return array_map(static fn (string $route): string => rtrim($route, '.'), $routes);
    }

    /**
     * @param  array<string, mixed>  $parameters
     *
     * @throws TenantNotFoundException
     */
    private function resolve(string $audience, Request $request, array $parameters): ?TenantSnapshotDto
    {
        // The webhook audience's route parameter is authoritative (§3.1) —
        // it outranks every header. The parameter is `tenantSlug`: a
        // hyphenated name (`{tenant-slug}`) registers but never matches.
        $slug = $parameters['tenantSlug'] ?? null;

        if ($audience === self::AUDIENCE_WEBHOOK && is_string($slug)) {
            return $this->requireServable($this->gateway->findBySlug($slug), 'unknown_webhook_slug');
        }

        if ($audience === self::AUDIENCE_MACHINE) {
            // From the bearer token alone — never a header.
            return $this->requireServable($this->machineTenant->resolve($request), 'invalid_machine_token');
        }

        $host = $this->hostAttribute($request, NormalizeTenantRequest::ATTR_HOST);

        if ($audience === self::AUDIENCE_ADMIN) {
            // Priority 1: X-Tenant-Id (admin only). The header names a
            // store; whether the caller may enter it is §3.2's decision.
            $id = $request->attributes->get(NormalizeTenantRequest::ATTR_TENANT_ID);

            if ($id !== null) {
                return $this->requireServable($this->gateway->find((int) $id), 'unknown_tenant_id');
            }

            // On a central API host the store comes from the caller's memberships.
            if ($this->hostMatches($host, 'central_hosts')) {
                return $this->selectByMembership($request);
            }
        } elseif ($audience === self::AUDIENCE_PUBLIC || $audience === self::AUDIENCE_USER) {
            // Priority 2: X-Tenant-Host (public/user only) — the
            // storefront-forwarded host (§7). Never honored on admin routes.
            $tenantHost = $this->hostAttribute($request, NormalizeTenantRequest::ATTR_TENANT_HOST);
            $resolved = $tenantHost === null ? null : $this->gateway->resolveByDomain($tenantHost);

            if ($resolved !== null) {
                return $this->requireServable($resolved, 'suspended_tenant_host');
            }
        }

        // Priority 3: the Host itself (API served on a tenant domain).
        $resolved = $host === null ? null : $this->gateway->resolveByDomain($host);

        if ($resolved !== null) {
            return $this->requireServable($resolved, 'suspended_host');
        }

        // Priority 4: the fallback list (dev/legacy hosts only).
        if ($this->hostMatches($host, 'fallback_hosts')) {
            $resolved = $this->gateway->defaultTenant();

            if ($resolved !== null) {
                return $this->requireServable($resolved, 'suspended_default_tenant');
            }
        }

        throw TenantNotFoundException::unresolved('unknown_host', ['host' => $host ?? '']);
    }

    /**
     * Admin default selection (§3.1): on a central host with no
     * X-Tenant-Id, exactly one active membership selects the store; several
     * need the header; none is refused. Null only for an unauthenticated
     * caller, whose own auth middleware answers.
     *
     * @throws TenantNotFoundException
     */
    private function selectByMembership(Request $request): ?TenantSnapshotDto
    {
        // Identification may read the principal; it grants nothing. The
        // guard is idempotent — resolving it early consumes no token.
        $user = Auth::guard('sanctum')->user();

        if ($user === null) {
            return null;
        }

        $memberships = $this->gateway->membershipsFor((int) $user->getAuthIdentifier());

        if (count($memberships) === 1) {
            return $this->requireServable(
                $this->gateway->find((int) $memberships[0]['id']),
                'membership_tenant_missing',
            );
        }

        if ($memberships === []) {
            throw TenantNotFoundException::noMembership();
        }

        throw TenantNotFoundException::selectionRequired(array_column($memberships, 'slug'));
    }

    private function hostAttribute(Request $request, string $key): ?string
    {
        $value = $request->attributes->get($key);

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function hostMatches(?string $host, string $list): bool
    {
        if ($host === null) {
            return false;
        }

        $entries = array_filter(array_map('trim', explode(',', (string) config('core.tenancy.'.$list, ''))));

        return in_array($host, $entries, true);
    }

    /**
     * A suspended store resolves — identification succeeded — but is not
     * served until it is reactivated (§3.1). The platform super-admin's
     * exemption on admin routes arrives with §3.2 (Phase 3).
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
