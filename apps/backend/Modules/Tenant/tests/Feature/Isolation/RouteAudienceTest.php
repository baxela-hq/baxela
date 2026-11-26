<?php

use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Contracts\Session\Middleware\AuthenticatesSessions;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Routing\Middleware\ThrottleRequestsWithRedis;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Modules\Core\Exceptions\TenantNotFoundException;
use Modules\Core\Http\Middleware\ResolveTenant;
use Modules\Core\Schemas\Shared\TenantColumn;
use Modules\Core\Support\Tenancy\TenantContext;
use Modules\Payment\Http\Middleware\ApplyDatabaseCredentials;
use Modules\Tenant\Models\TenantCurrency;
use Modules\Tenant\Schemas\TenantCurrency\TenantCurrencySchema;
use Modules\Tenant\Tests\Feature\HelperTrait;
use Modules\Tenant\Tests\Fixtures\RouteStack;
use Modules\Webhooks\Http\Middleware\EnsureMachineToken;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

it('classifies every API route; nothing is left unclassified', function () {
    $apiRoutes = 0;
    $unclassified = [];
    $unregisteredOnboarding = [];

    foreach (Route::getRoutes()->getRoutes() as $route) {
        if (! in_array('api', $route->gatherMiddleware(), true)) {
            continue;
        }

        $apiRoutes++;

        try {
            $audience = ResolveTenant::audienceOf((string) $route->getName());
        } catch (TenantNotFoundException) {
            $unclassified[] = $route->uri();

            continue;
        }

        $allowed = (array) config('core.tenancy.onboarding_routes', []);

        if ($audience === ResolveTenant::AUDIENCE_ONBOARDING && ! in_array($route->getName(), $allowed, true)) {
            $unregisteredOnboarding[] = $route->uri();
        }
    }

    expect($apiRoutes)->toBeGreaterThan(400)
        ->and($unclassified)->toBe([])
        ->and($unregisteredOnboarding)->toBe([]);
});

it('fails closed on an unclassified, an unnamed, and an unregistered onboarding route', function () {
    Route::middleware('api')->name('api.tenant.weird.thing')->get('/api/v1/tenant-probe/unclassified', fn () => ['no']);
    Route::middleware('api')->get('/api/v1/tenant-probe/unnamed', fn () => ['no']);
    Route::middleware('api')->name('api.tenant.onboarding.sneaky')->get('/api/v1/tenant-probe/sneaky', fn () => ['no']);

    $this->getJson('/api/v1/tenant-probe/unclassified')
        ->assertStatus(404)
        ->assertJsonPath('code', 'core.tenant.not_found')
        ->assertJsonPath('meta.reason', 'unclassified_route');

    $this->getJson('/api/v1/tenant-probe/unnamed')
        ->assertStatus(404)
        ->assertJsonPath('meta.reason', 'unclassified_route');

    $this->getJson('/api/v1/tenant-probe/sneaky')
        ->assertStatus(404)
        ->assertJsonPath('meta.reason', 'unregistered_onboarding_route');
});

it('serves the platform routes on any host, with no tenant context', function () {
    $this->productionLikeTenancy();

    $user = $this->plainUser();

    $this->actingAs($user, 'sanctum')
        ->getJson($this->onHost('unknown.example.test', '/api/user'))
        ->assertStatus(200)
        ->assertJsonPath('id', $user->getKey());

    expect(app(TenantContext::class)->mode())->toBe(TenantContext::MODE_INACTIVE);
});

it('extends the middleware priority list without disturbing Laravel defaults', function () {
    $priority = app(HttpKernel::class)->getMiddlewarePriority();

    $laravelDefaults = [
        HandlePrecognitiveRequests::class,
        EncryptCookies::class,
        AddQueuedCookiesToResponse::class,
        StartSession::class,
        ShareErrorsFromSession::class,
        AuthenticatesRequests::class,
        ThrottleRequests::class,
        ThrottleRequestsWithRedis::class,
        AuthenticatesSessions::class,
        SubstituteBindings::class,
        Authorize::class,
    ];

    // Every default is still there, in its original relative order.
    expect(array_values(array_intersect($priority, $laravelDefaults)))->toBe($laravelDefaults);

    $position = fn (string $class): int => (int) array_search($class, $priority, true);

    expect($position(ResolveTenant::class))->toBeLessThan($position(ApplyDatabaseCredentials::class))
        ->and($position(ApplyDatabaseCredentials::class))->toBeLessThan($position(AuthenticatesRequests::class))
        ->and($position(ResolveTenant::class))->toBeLessThan($position(ThrottleRequests::class))
        ->and($position(ResolveTenant::class))->toBeLessThan($position(SubstituteBindings::class));
});

it('puts tenant identification first on representative admin, machine and throttled routes', function () {
    $routes = [
        'api.tenant.admin.tenants.list' => [Authenticate::class, false],
        'api.webhooks.machine.orders.list' => [EnsureMachineToken::class, true],
    ];

    foreach ($routes as $name => [$guard, $throttled]) {
        $stack = RouteStack::sorted($name);
        // Middleware carry their parameters in the stack, e.g. `Authenticate:sanctum`.
        $position = fn (string $class): int => (int) collect($stack)->search(
            fn (string $middleware): bool => Str::before($middleware, ':') === $class,
        );

        // Identification, then credentials, and only afterwards the
        // route's own guard, route-model binding and — where present —
        // throttling.
        expect($stack[0])->toBe(ResolveTenant::class)
            ->and($stack[1])->toBe(ApplyDatabaseCredentials::class)
            ->and($position($guard))->toBeGreaterThan(1)
            ->and($position(SubstituteBindings::class))->toBeGreaterThan(0);

        if ($throttled) {
            expect($position(ThrottleRequests::class))->toBeGreaterThan(0);
        }
    }
});

it('binds route models under the resolved tenant, before the controller runs', function () {
    $acme = $this->createTenant('acme', 'shop-acme.test');
    $other = $this->createTenant('other', 'shop-other.test');
    $context = app(TenantContext::class);

    $context->forTenant((int) $other->getKey());
    $foreign = TenantCurrency::query()->create([TenantCurrencySchema::CURRENCY_ID => 7]);
    $context->forTenant((int) $acme->getKey());
    $own = TenantCurrency::query()->create([TenantCurrencySchema::CURRENCY_ID => 7]);
    $context->reset();

    expect($foreign->getAttribute(TenantColumn::TENANT_ID))->not->toBe($own->getAttribute(TenantColumn::TENANT_ID));

    Route::middleware('api')->name('api.tenant.public.probe.currency')
        ->get('/api/v1/tenant-probe/currencies/{currency}', fn (TenantCurrency $currency) => [
            'id' => $currency->getKey(),
        ]);

    // The other store's row is invisible to the binding: a 404, not a leak.
    $this->getJson($this->onHost('shop-acme.test', '/api/v1/tenant-probe/currencies/'.$foreign->getKey()))
        ->assertStatus(404)
        ->assertJsonPath('code', 'http.404');

    $this->getJson($this->onHost('shop-acme.test', '/api/v1/tenant-probe/currencies/'.$own->getKey()))
        ->assertStatus(200)
        ->assertJsonPath('id', $own->getKey());
});
