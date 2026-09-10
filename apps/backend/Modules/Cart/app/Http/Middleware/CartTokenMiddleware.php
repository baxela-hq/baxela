<?php

namespace Modules\Cart\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Modules\Cart\Exceptions\Public\CartTokenException;

/**
 * The X-Cart-Token header is a bearer credential for the guest cart: it is
 * accepted from the header only (never query parameters or body), must be a
 * UUID minted client-side with a CSPRNG, and is validated before any guest
 * cart route runs. The raw value is never logged or echoed in responses.
 */
class CartTokenMiddleware
{
    public const string HEADER = 'X-Cart-Token';

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        $token = $request->header(self::HEADER);

        if (empty($token)) {
            throw new CartTokenException(missing: true);
        }

        if (! Str::isUuid($token)) {
            throw new CartTokenException(missing: false);
        }

        return $next($request);
    }
}
