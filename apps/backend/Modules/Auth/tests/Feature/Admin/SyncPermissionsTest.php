<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Modules\Auth\Models\Permission;
use Modules\Auth\Schemas\GuardsEnum;
use Modules\Auth\Schemas\Permission\PermissionSchema;

uses(RefreshDatabase::class);

it('creates a permission per admin route with the web guard', function () {
    $this->artisan('auth:sync-permissions');

    // Routes reserved for the platform super-admin get no permission row:
    // what has no row can never be assigned to a role.
    $superAdminOnly = fn (string $name): bool => collect((array) config('core.tenancy.super_admin_only_routes', []))
        ->contains(fn (string $prefix): bool => str_starts_with($name, $prefix));

    $adminRouteNames = collect(Route::getRoutes())
        ->map(fn ($route) => $route->getName())
        ->filter(fn ($name) => $name !== null && str_starts_with($name, 'api.') && str_contains($name, '.admin.'))
        ->values();

    expect($adminRouteNames->count())->toBeGreaterThan(50);
    expect(Permission::count())->toBe($adminRouteNames->filter(fn ($name) => ! $superAdminOnly($name))->count());
    expect(Permission::query()->where('name', 'auth.admin.roles.list')->exists())->toBeTrue();
    expect(Permission::query()->where('name', 'auth.admin.account.show')->exists())->toBeTrue();
    expect(Permission::query()->where('guard_name', GuardsEnum::WEB->value)->count())->toBe(Permission::count());
});

it('never creates an assignable permission for the super-admin-only routes', function () {
    $this->artisan('auth:sync-permissions');

    /** @var list<string> $prefixes */
    $prefixes = (array) config('core.tenancy.super_admin_only_routes');

    $reserved = [];
    foreach (Route::getRoutes()->getRoutes() as $route) {
        $name = $route->getName();

        if (is_string($name) && Str::startsWith($name, $prefixes)) {
            $reserved[] = Str::after($name, 'api.');
        }
    }

    expect($reserved)->not->toBe([]);

    // What has no row can never be granted to a role.
    expect(Permission::query()->whereIn(PermissionSchema::NAME, $reserved)->exists())->toBeFalse();
});

it('is idempotent on rerun', function () {
    $this->artisan('auth:sync-permissions');
    $count = Permission::count();

    $this->artisan('auth:sync-permissions');

    expect(Permission::count())->toBe($count);
});
