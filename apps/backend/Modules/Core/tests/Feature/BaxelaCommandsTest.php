<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Process;
use Mockery\MockInterface;
use Modules\Core\Extensions\ComposerPackages;
use Modules\Core\Extensions\ExtensionManager;
use Modules\Core\Models\Extension as ExtensionRow;
use Modules\Core\Schemas\Extension\ExtensionSchema;
use Modules\Core\Tests\Feature\HelperTrait;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

beforeEach(function () {
    ExtensionManager::flush();
    // Composer never really runs in tests — the baxela:* commands
    // stream it through Laravel's Process facade precisely so it can
    // be faked here.
    Process::fake();
});

it('refuses malformed package names without touching composer', function (string $command) {
    $this->artisan($command, ['package' => 'Not A Slug'])->assertFailed();

    Process::assertNothingRan();

    expect(ExtensionRow::count())->toBe(0);
})->with([
    'baxela:install',
    'baxela:remove',
    'baxela:enable',
    'baxela:disable',
]);

it('rolls back a required package that is not a baxela extension', function () {
    // The slug gate only lets baxela-* names through, so the type check
    // needs a package record that claims one while typing as a plain
    // library — the container mock supplies exactly that.
    $this->mock(ComposerPackages::class, function (MockInterface $mock) {
        $mock->shouldReceive('package')->andReturn(['name' => 'acme/baxela-demo', 'type' => 'library']);
        $mock->shouldReceive('isExtensionPackage')->andReturn(false);
    });

    $this->artisan('baxela:install', ['package' => 'acme/baxela-demo'])->assertFailed();

    expect(ExtensionRow::count())->toBe(0);

    // The refused require does not stand: composer remove must have run.
    Process::assertRan(fn ($process) => in_array('remove', $process->command, true)
        && in_array('acme/baxela-demo', $process->command, true));
});

it('keeps no row when the composer require itself fails', function () {
    Process::fake(['*' => Process::result(exitCode: 1, output: 'Resolution failed')]);

    $this->artisan('baxela:install', ['package' => 'acme/baxela-ghost'])->assertFailed();

    expect(ExtensionRow::count())->toBe(0);
});

it('enables and disables an installed extension without composer', function () {
    ExtensionRow::query()->create([
        ExtensionSchema::SLUG => 'acme/baxela-sandbox',
        ExtensionSchema::ENABLED => false,
    ]);

    $this->artisan('baxela:disable', ['package' => 'acme/baxela-sandbox'])->assertSuccessful();

    Process::assertNothingRan();

    $this->artisan('baxela:enable', ['package' => 'acme/baxela-sandbox'])
        ->assertFailed(); // package absent from vendor: enable refuses the leftover row

    expect(ExtensionRow::query()->value(ExtensionSchema::ENABLED))->toBeFalse();
});

it('refuses toggling an extension that is not installed', function () {
    $this->artisan('baxela:disable', ['package' => 'acme/baxela-ghost'])->assertFailed();
    $this->artisan('baxela:update', ['package' => 'acme/baxela-ghost'])->assertFailed();
});

it('lists rows with a live version and the skip reason', function () {
    ExtensionRow::query()->create([
        ExtensionSchema::SLUG => 'acme/baxela-sandbox',
        ExtensionSchema::ENABLED => true,
    ]);

    // Full-output capture, not expectsOutputToContain: the framework
    // matches those one Mockery expectation per output chunk, and the
    // slug and reason share the table-row chunk — two expectations on
    // one chunk always starve the second.
    expect(Artisan::call('baxela:list'))->toBe(0)
        ->and(Artisan::output())->toContain('acme/baxela-sandbox')
        ->toContain('not_installed');
});
