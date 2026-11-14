<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Mockery\MockInterface;
use Modules\Auth\Models\Permission;
use Modules\Auth\Models\User;
use Modules\Auth\Schemas\GuardsEnum;
use Modules\Core\Actions\Admin\Extension\CheckExtensionUpdatesAction;
use Modules\Core\Extensions\ComposerPackages;
use Modules\Core\Extensions\ExtensionManager;
use Modules\Core\Models\Extension as ExtensionRow;
use Modules\Core\Schemas\Extension\ExtensionSchema;
use Modules\Core\Tests\Feature\HelperTrait;

/*
 * Marketplace update-check proxy (P5.3). Multiple HTTP calls share the
 * process — cache and config state persist between them, exactly the
 * persistence model the two-key cache must survive.
 */

uses(RefreshDatabase::class);
uses(HelperTrait::class);

const MARKETPLACE_HOST = 'marketplace.test';

const SLUG = 'acme/baxela-sandbox';

beforeEach(function () {
    ExtensionManager::flush();
    Illuminate\Support\Facades\Cache::forget(CheckExtensionUpdatesAction::CACHE_KEY);
    Illuminate\Support\Facades\Cache::forget(CheckExtensionUpdatesAction::STALE_KEY);
    config(['core.extensions.marketplace_url' => 'https://'.MARKETPLACE_HOST]);
});

/** @param array<string, ?string> $map */
function mockInstalledExtensions(array $map): void
{
    test()->mock(ComposerPackages::class, function (MockInterface $mock) use ($map) {
        $mock->shouldReceive('extensionSlugs')->andReturn(array_keys($map));
        $mock->shouldReceive('version')->andReturnUsing(fn (string $slug) => $map[$slug] ?? null);
        // The toggle action's guards consult these; permissive defaults.
        $mock->shouldReceive('package')->andReturn(['name' => SLUG, 'type' => 'baxela-extension']);
    });
}

/** @param array<string, mixed> $data */
function marketplaceOk(array $data = []): void
{
    Http::fake([
        MARKETPLACE_HOST.'/*' => Http::response(['data' => $data + [
            SLUG => ['state' => 'up_to_date', 'latest' => '0.0.1', 'update_available' => false, 'current_yanked' => false],
        ]]),
    ]);
}

function primeCache(): string
{
    mockInstalledExtensions([SLUG => '0.0.1']);
    // Fakes MERGE (first match wins): a test that installed its own
    // mutable fake before priming keeps it for the priming fetch.
    marketplaceOk();
    test()->getJson(test()->adminUrl('/extensions/updates'))->assertOk();

    $generated = Cache::get(CheckExtensionUpdatesAction::STALE_KEY)['generated_at'];
    test()->assertIsString($generated);

    return $generated;
}

it('rejects guests and permission-less users', function () {
    $this->getJson($this->adminUrl('/extensions/updates'))->assertUnauthorized();

    $this->actingAs(User::factory()->active()->create());
    $this->getJson($this->adminUrl('/extensions/updates'))->assertForbidden();
});

it('serves the derived permission', function () {
    $user = User::factory()->active()->create();
    $user->givePermissionTo(
        Permission::query()->firstOrCreate([
            'name' => 'core.admin.extensions.updates',
            'guard_name' => GuardsEnum::WEB->value,
        ])
    );
    $this->actingAs($user);

    mockInstalledExtensions([]);

    $this->getJson($this->adminUrl('/extensions/updates'))
        ->assertOk()
        ->assertJsonPath('data.availability', 'ok')
        ->assertJsonPath('data.data', []);
});

it('reports an unconfigured marketplace without any http call', function () {
    config(['core.extensions.marketplace_url' => '']);
    Http::fake();

    $this->actingAs($this->superAdminUser());

    $this->getJson($this->adminUrl('/extensions/updates'))
        ->assertOk()
        ->assertJsonPath('data.availability', 'marketplace_unconfigured')
        ->assertJsonPath('data.cached', false)
        ->assertJsonPath('data.data', []);

    Http::assertNothingSent();
});

it('short-circuits an empty installed map without http', function () {
    Http::fake();
    $this->actingAs($this->superAdminUser());

    // A leftover registry row with no package version is skipped — the
    // map is empty even though a row exists.
    ExtensionRow::query()->create([
        ExtensionSchema::SLUG => SLUG,
        ExtensionSchema::ENABLED => true,
    ]);
    mockInstalledExtensions([SLUG => null]);

    $this->getJson($this->adminUrl('/extensions/updates'))
        ->assertOk()
        ->assertJsonPath('data.availability', 'ok')
        ->assertJsonPath('data.data', []);

    Http::assertNothingSent();
});

it('fetches fresh, sends the exact contract payload, then serves the fresh cache', function () {
    marketplaceOk();
    $this->actingAs($this->superAdminUser());
    mockInstalledExtensions([SLUG => '0.0.1']);

    $first = $this->getJson($this->adminUrl('/extensions/updates'))
        ->assertOk()
        ->assertJsonPath('data.availability', 'ok')
        ->assertJsonPath('data.cached', false)
        ->assertJsonPath('data.generated_at', fn (?string $at) => $at !== null && $at !== '')
        ->assertJsonPath('data.data.'.SLUG.'.state', 'up_to_date');

    // The exact marketplace contract: core version + slug=>semver map.
    Http::assertSent(function ($request): bool {
        if (! str_contains($request->url(), '/api/packages/updates')) {
            return false;
        }

        return $request['core'] === '0.1.0'
            && $request['installed'] === [SLUG => '0.0.1'];
    });

    $second = $this->getJson($this->adminUrl('/extensions/updates'))
        ->assertOk()
        ->assertJsonPath('data.availability', 'ok')
        ->assertJsonPath('data.cached', true);

    // Same payload from cache — no second outbound request.
    Http::assertSentCount(1);
    expect($second->json('data.generated_at'))->toBe($first->json('data.generated_at'));
});

it('serves stale data with its original timestamp when the fresh cache expired', function () {
    $this->actingAs($this->superAdminUser());
    $generated = primeCache();
    mockInstalledExtensions([SLUG => '0.0.1']);

    // Fresh (15 min) expires; the marketplace then goes down.
    $this->travel(16)->minutes();
    Http::fake([
        MARKETPLACE_HOST.'/*' => Http::failedConnection(),
    ]);

    $this->getJson($this->adminUrl('/extensions/updates'))
        ->assertOk()
        ->assertJsonPath('data.availability', 'marketplace_unreachable')
        ->assertJsonPath('data.cached', true)
        ->assertJsonPath('data.generated_at', $generated)
        ->assertJsonPath('data.data.'.SLUG.'.state', 'up_to_date');

    // The failed fetch never rewrote the stale entry.
    expect(Cache::get(CheckExtensionUpdatesAction::STALE_KEY)['generated_at'])->toBe($generated);
});

it('degrades to an empty map once the stale retention lapses', function () {
    $this->actingAs($this->superAdminUser());
    primeCache();
    mockInstalledExtensions([SLUG => '0.0.1']);

    $this->travel(25)->hours();
    Http::fake([
        MARKETPLACE_HOST.'/*' => Http::failedConnection(),
    ]);

    $this->getJson($this->adminUrl('/extensions/updates'))
        ->assertOk()
        ->assertJsonPath('data.availability', 'marketplace_unreachable')
        ->assertJsonPath('data.cached', false)
        ->assertJsonPath('data.generated_at', null)
        ->assertJsonPath('data.data', []);
});

it('classifies http statuses precisely', function (int $status, string $availability) {
    $this->actingAs($this->superAdminUser());
    mockInstalledExtensions([SLUG => '0.0.1']);

    Http::fake([
        MARKETPLACE_HOST.'/*' => Http::response(['data' => []], $status),
    ]);

    $this->getJson($this->adminUrl('/extensions/updates'))
        ->assertOk()
        ->assertJsonPath('data.availability', $availability)
        ->assertJsonPath('data.cached', false)
        ->assertJsonPath('data.data', []);
})->with([
    'server error' => [500, 'marketplace_unreachable'],
    'throttled' => [429, 'marketplace_error'],
    'validation' => [422, 'marketplace_error'],
    'redirect' => [302, 'marketplace_error'],
    'no content' => [204, 'marketplace_error'],
    'not found' => [404, 'marketplace_error'],
]);

it('rejects malformed successful responses', function (string $body) {
    $this->actingAs($this->superAdminUser());
    mockInstalledExtensions([SLUG => '0.0.1']);

    Http::fake([
        MARKETPLACE_HOST.'/*' => Http::response($body, 200),
    ]);

    $dbg = $this->getJson($this->adminUrl('/extensions/updates'));
    fwrite(STDERR, '[dbg2] '.substr($dbg->getContent(), 0, 300)."\n");
    $dbg->assertOk()
        ->assertJsonPath('data.availability', 'marketplace_error')
        ->assertJsonPath('data.cached', false)
        ->assertJsonPath('data.data', []);
})->with([
    'invalid json' => ['not json at all'],
    'missing data key' => ['{"meta": {}}'],
    'non-array data' => ['{"data": "nope"}'],
]);

it('serves stale data alongside a marketplace_error classification', function () {
    $this->actingAs($this->superAdminUser());

    // A swappable server closure: reassigning it flips the marketplace's
    // answer without a second Http::fake() (stub fakes merge and never
    // replace — the FxSyncTest gotcha).
    $serve = fn () => Http::response(['data' => [SLUG => ['state' => 'up_to_date', 'latest' => '0.0.1', 'update_available' => false, 'current_yanked' => false]]]);
    Http::fake([
        MARKETPLACE_HOST.'/*' => function () use (&$serve) {
            return $serve();
        },
    ]);
    $generated = primeCache();
    mockInstalledExtensions([SLUG => '0.0.1']);

    $this->travel(16)->minutes();
    $serve = fn () => Http::response(['message' => 'The [core] value is not a parseable semver version.'], 422);

    $this->getJson($this->adminUrl('/extensions/updates'))
        ->assertOk()
        ->assertJsonPath('data.availability', 'marketplace_error')
        ->assertJsonPath('data.cached', true)
        ->assertJsonPath('data.generated_at', $generated)
        ->assertJsonPath('data.data.'.SLUG.'.state', 'up_to_date');
});

it('invalidates both cache keys on toggle', function () {
    marketplaceOk();
    $this->actingAs($this->superAdminUser());
    mockInstalledExtensions([SLUG => '0.0.1']);

    ExtensionRow::query()->create([
        ExtensionSchema::SLUG => SLUG,
        ExtensionSchema::ENABLED => false,
    ]);

    $this->getJson($this->adminUrl('/extensions/updates'))->assertOk();
    Http::assertSentCount(1);

    // The toggle must invalidate: the next check fetches again.
    $this->postJson($this->adminUrl('/extensions/acme/baxela-sandbox/enable'))->assertOk();

    $this->getJson($this->adminUrl('/extensions/updates'))->assertOk();
    Http::assertSentCount(2);
});

it('invalidates after a successful install require and its rollback remove', function () {
    marketplaceOk();
    Process::fake();
    $this->actingAs($this->superAdminUser());

    // The install flow: require succeeds, then the type check refuses →
    // rollback removes. Both composer successes must invalidate. No row
    // exists, so the command proceeds to the require; composer slugs
    // keep the update-check map non-empty on both fetches.
    $this->mock(ComposerPackages::class, function (MockInterface $mock) {
        $mock->shouldReceive('extensionSlugs')->andReturn([SLUG]);
        $mock->shouldReceive('version')->andReturn('0.0.1');
        $mock->shouldReceive('package')->andReturn(['name' => SLUG, 'type' => 'library']);
        $mock->shouldReceive('isExtensionPackage')->andReturn(false);
    });

    $this->getJson($this->adminUrl('/extensions/updates'))->assertOk();
    Http::assertSentCount(1);

    $this->artisan('baxela:install', ['package' => SLUG])->assertFailed();

    // Two composer mutations happened (require + rollback remove): the
    // cache is gone and the next check fetches fresh.
    $this->getJson($this->adminUrl('/extensions/updates'))->assertOk();
    Http::assertSentCount(2);

    Process::assertRan(fn ($process) => in_array('remove', $process->command, true));
});

it('invalidates after a successful remove', function () {
    marketplaceOk();
    Process::fake();
    $this->actingAs($this->superAdminUser());

    $this->mock(ComposerPackages::class, function (MockInterface $mock) {
        $mock->shouldReceive('extensionSlugs')->andReturn([SLUG]);
        $mock->shouldReceive('version')->andReturn('0.0.1');
        $mock->shouldReceive('package')->andReturn(['name' => SLUG, 'type' => 'baxela-extension']);
        $mock->shouldReceive('providerFor')->andReturn(null);
    });

    ExtensionRow::query()->create([
        ExtensionSchema::SLUG => SLUG,
        ExtensionSchema::ENABLED => true,
    ]);

    $this->getJson($this->adminUrl('/extensions/updates'))->assertOk();
    Http::assertSentCount(1);

    $this->artisan('baxela:remove', ['package' => SLUG])->assertSuccessful();

    $this->getJson($this->adminUrl('/extensions/updates'))->assertOk();
    Http::assertSentCount(2);
});

it('invalidates after a successful update', function () {
    marketplaceOk();
    Process::fake();
    $this->actingAs($this->superAdminUser());

    $this->mock(ComposerPackages::class, function (MockInterface $mock) {
        $mock->shouldReceive('extensionSlugs')->andReturn([SLUG]);
        $mock->shouldReceive('version')->andReturn('0.0.2');
        $mock->shouldReceive('package')->andReturn(['name' => SLUG, 'type' => 'baxela-extension']);
        $mock->shouldReceive('compatible')->andReturn(true);
    });

    ExtensionRow::query()->create([
        ExtensionSchema::SLUG => SLUG,
        ExtensionSchema::ENABLED => true,
    ]);

    $this->getJson($this->adminUrl('/extensions/updates'))->assertOk();
    Http::assertSentCount(1);

    $this->artisan('baxela:update', ['package' => SLUG])->assertSuccessful();

    $this->getJson($this->adminUrl('/extensions/updates'))->assertOk();
    Http::assertSentCount(2);
});
