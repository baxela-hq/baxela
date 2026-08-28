<?php

namespace Modules\Core\Http\Middleware;

use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Modules\Core\Utils\Auth;

class AdminMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @throws AuthorizationException
     */
    public function handle(Request $request, Closure $next)
    {
        if (! Auth::check()) {
            throw new AuthorizationException;
        }

        $user = Auth::user();
        if (! $user->is_admin) {
            throw new AuthorizationException;
        }

        return $next($request);
    }
}
