<?php

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Middleware\PermissionMiddleware;

it('protects every admin route with auth and permission middleware', function () {
    $adminRoutes = collect(Route::getRoutes())
        ->filter(fn ($route) => str_starts_with($route->uri(), 'api/v1/')
            && str_contains($route->uri(), '/admin'));

    // Guard against a vacuous pass: the URI convention itself must hold.
    expect($adminRoutes->count())->toBeGreaterThan(0);

    $offenders = $adminRoutes
        ->map(fn ($route) => [
            'uri' => $route->uri(),
            'middleware' => $route->gatherMiddleware(),
        ])
        ->filter(function (array $route) {
            $hasAuth = in_array('auth:sanctum', $route['middleware'], true);

            $hasPermission = collect($route['middleware'])->contains(
                fn ($middleware) => $middleware === PermissionMiddleware::class
                    || $middleware === 'permission'
                    || str_starts_with((string) $middleware, 'permission:')
            );

            return ! $hasAuth || ! $hasPermission;
        });

    if ($offenders->isNotEmpty()) {
        $listing = $offenders
            ->map(fn (array $route) => '  '.$route['uri'].'  ['.$route['method'].']  →  '.implode(', ', $route['middleware']))
            ->implode("\n");

        $this->fail(
            "Admin routes missing 'auth:sanctum' or ".PermissionMiddleware::class.":\n".$listing
        );
    }

    expect($offenders)->toBeEmpty();
});

it('denies a guest on every admin route with 401', function () {
    $sampled = collect(Route::getRoutes())
        ->filter(fn ($route) => str_starts_with($route->uri(), 'api/v1/')
            && str_contains($route->uri(), '/admin')
            && in_array('GET', $route->methods(), true)
            && ! str_contains($route->uri(), '{'))
        ->take(3);

    $sampled->each(function ($route) {
        $this->getJson('/'.$route->uri())
            ->assertStatus(401)
            ->assertJsonPath('code', 'http.401');
    });
});
