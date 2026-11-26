<?php

namespace Modules\Tenant\Tests\Fixtures;

use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Routing\SortedMiddleware;
use Illuminate\Support\Facades\Route;

/** Reads a named route's effective middleware, in execution order. */
final class RouteStack
{
    /** @return list<string> */
    public static function sorted(string $name): array
    {
        $aliases = Route::getMiddleware();
        $groups = Route::getMiddlewareGroups();

        $middleware = [];
        foreach (Route::getRoutes()->getByName($name)->gatherMiddleware() as $item) {
            if (isset($groups[$item])) {
                $middleware = [...$middleware, ...$groups[$item]];

                continue;
            }

            // An alias with parameters, e.g. `auth:sanctum` → `Authenticate:sanctum`.
            $segments = explode(':', $item, 2);
            $class = $aliases[$segments[0]] ?? $segments[0];
            $middleware[] = isset($segments[1]) ? $class.':'.$segments[1] : $class;
        }

        return (new SortedMiddleware(app(HttpKernel::class)->getMiddlewarePriority(), $middleware))->all();
    }
}
