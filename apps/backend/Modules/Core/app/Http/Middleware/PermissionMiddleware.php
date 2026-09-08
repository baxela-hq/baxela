<?php

namespace Modules\Core\Http\Middleware;

use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Modules\Core\Contracts\Gateways\Auth\AccessGatewayInterface;
use Modules\Core\Utils\Auth;

class PermissionMiddleware
{
    /**
     * Fail-closed: every route reaching this middleware must carry an admin
     * route name (`api.{module}.admin.{resource}.{action}`); the matching
     * permission is derived from it and enforced through the access gateway.
     *
     * @throws AuthorizationException
     */
    public function handle(Request $request, Closure $next)
    {
        if (! Auth::check()) {
            throw new AuthorizationException;
        }

        $name = $request->route()?->getName();

        if ($name === null || ! preg_match('/^api\.[^.]+\.admin\.[^.]+(\.[^.]+)*$/', $name)) {
            throw new AuthorizationException;
        }

        $permission = Str::after($name, 'api.');

        if (! app(AccessGatewayInterface::class)->userCan(Auth::user(), $permission)) {
            throw new AuthorizationException;
        }

        return $next($request);
    }
}
