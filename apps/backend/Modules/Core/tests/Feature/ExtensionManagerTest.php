<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Extensions\ExtensionManager;
use Modules\Core\Models\Extension as ExtensionRow;
use Modules\Core\Schemas\Extension\ExtensionSchema;
use Modules\Core\Tests\Feature\HelperTrait;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

beforeEach(function () {
    ExtensionManager::flush();
});

it('caches state for the request: writes behind the manager stay invisible until flush', function () {
    $manager = app(ExtensionManager::class);
    $manager->install('baxela/sandbox');

    // First read snapshots the state for this request…
    expect($manager->enabled('baxela/sandbox'))->toBeTrue();

    // …so an out-of-band write (another process, a raw query) must not
    // leak into this request's decisions…
    ExtensionRow::query()
        ->where(ExtensionSchema::SLUG, 'baxela/sandbox')
        ->update([ExtensionSchema::ENABLED => false]);

    expect($manager->enabled('baxela/sandbox'))->toBeTrue();

    // …until the cache is deliberately busted, as the commands do.
    ExtensionManager::flush();

    expect($manager->enabled('baxela/sandbox'))->toBeFalse();
});

it('writes enable/disable through to the database and the cache', function () {
    $manager = app(ExtensionManager::class);
    $manager->install('baxela/sandbox');

    $manager->disable('baxela/sandbox');

    expect($manager->enabled('baxela/sandbox'))->toBeFalse()
        ->and(ExtensionRow::query()->where(ExtensionSchema::SLUG, 'baxela/sandbox')->value(ExtensionSchema::ENABLED))->toBeFalse();

    $manager->enable('baxela/sandbox');

    expect($manager->enabled('baxela/sandbox'))->toBeTrue()
        ->and(ExtensionRow::query()->where(ExtensionSchema::SLUG, 'baxela/sandbox')->value(ExtensionSchema::ENABLED))->toBeTrue();
});

it('installs idempotently and removes cleanly', function () {
    $manager = app(ExtensionManager::class);

    $manager->install('baxela/sandbox');
    $manager->install('baxela/sandbox');

    expect(ExtensionRow::count())->toBe(1);

    $manager->remove('baxela/sandbox');

    expect(ExtensionRow::count())->toBe(0)
        ->and($manager->row('baxela/sandbox'))->toBeNull();
});

it('answers enabled=false for a slug with no row', function () {
    expect(app(ExtensionManager::class)->enabled('baxela/never-installed'))->toBeFalse();
});
