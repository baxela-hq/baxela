<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Modules\Auth\Models\Permission;
use Modules\Auth\Schemas\GuardsEnum;
use Tests\TestCase;

uses(TestCase::class);
uses(RefreshDatabase::class);

it('creates a permission per admin route with the web guard', function () {
    $this->artisan('auth:sync-permissions');

    $adminRouteCount = collect(Route::getRoutes())
        ->map(fn ($route) => $route->getName())
        ->filter(fn ($name) => $name !== null && str_starts_with($name, 'api.') && str_contains($name, '.admin.'))
        ->count();

    expect($adminRouteCount)->toBeGreaterThan(50);
    expect(Permission::count())->toBe($adminRouteCount);
    expect(Permission::query()->where('name', 'auth.admin.roles.list')->exists())->toBeTrue();
    expect(Permission::query()->where('name', 'auth.admin.account.show')->exists())->toBeTrue();
    expect(Permission::query()->where('guard_name', GuardsEnum::WEB->value)->count())->toBe(Permission::count());
});

it('is idempotent on rerun', function () {
    $this->artisan('auth:sync-permissions');
    $count = Permission::count();

    $this->artisan('auth:sync-permissions');

    expect(Permission::count())->toBe($count);
});
