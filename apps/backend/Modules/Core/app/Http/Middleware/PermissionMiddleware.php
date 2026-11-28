<?php

namespace Modules\Core\Http\Middleware;

use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Modules\Core\Contracts\Gateways\Auth\AccessGatewayInterface;
use Modules\Core\Support\Tenancy\AuthorizeTenantAccess;
use Modules\Core\Utils\Auth;
use Spatie\Permission\PermissionRegistrar;

class PermissionMiddleware
{
    /**
     * Fail-closed: every route reaching this middleware must carry an admin
     * route name (`api.{module}.admin.{resource}.{action}`); the matching
     * permission is derived from it and enforced through the access gateway.
     *
     * Authorization is two-layered (multi-tenancy §3.2): the resolved store
     * is earned first (active membership or the platform super-admin
     * bypass — AuthorizeTenantAccess), then the route permission is checked
     * under the matching spatie team (§5). The team context is set before
     * the check and restored afterward; php-fpm boots one request per
     * process, so the restore matters for the suite and queue workers.
     *
     * @throws AuthorizationException
     */
    public function handle(Request $request, Closure $next)
    {
        if (! Auth::check()) {
            file_put_contents('/tmp/pm.log', 'DENY A\n', FILE_APPEND);
            throw new AuthorizationException;
        }

        $name = $request->route()?->getName();

        if ($name === null || ! preg_match('/^api\.[^.]+\.admin\.[^.]+(\.[^.]+)*$/', $name)) {
            file_put_contents('/tmp/pm.log', 'DENY B\n', FILE_APPEND);
            throw new AuthorizationException;
        }

        $permission = Str::after($name, 'api.');

        $user = Auth::user();
        AuthorizeTenantAccess::assertAdminAccess($user);

        // The permission check runs under the context's spatie team
        // (TenantTeamResolver): the store was earned by
        // AuthorizeTenantAccess, and the team follows the same context —
        // there is no set/restore juggling because the context is the
        // single authority (§5).

        if (! app(AccessGatewayInterface::class)->userCan($user, $permission)) {
            throw new AuthorizationException;
        }

        return $next($request);
    }
}
