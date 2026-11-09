<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Extensions\Extension;
use Modules\Core\Extensions\ExtensionManager;
use Modules\Core\Models\Extension as ExtensionRow;
use Modules\Core\Schemas\Extension\ExtensionSchema;
use Modules\Core\Tests\Feature\HelperTrait;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

beforeEach(function () {
    ExtensionManager::flush();
});

/**
 * A minimal real extension: registers a payment driver slug in its
 * extensionBoot — exactly how a gateway extension merges itself in.
 * The merged config key is the observable for whether the guarded
 * boot path ran.
 *
 * @param  array<string, mixed>  $manifest
 */
function guardExtensionProvider(array $manifest): Extension
{
    return new class($manifest) extends Extension
    {
        /** @param array<string, mixed> $manifestArg */
        public function __construct(protected array $manifestArg)
        {
            parent::__construct(app());
        }

        /** @return array<string, mixed> */
        public function manifest(): array
        {
            return $this->manifestArg;
        }

        protected function extensionBoot(): void
        {
            config(['payment.drivers.guard-test' => 'GuardTestDriver']);
        }
    };
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function guardManifest(array $overrides = []): array
{
    return array_merge([
        'slug' => 'baxela/guard-test',
        'name' => 'Guard Test',
        'type' => 'payment-driver',
        'version' => '0.0.1',
        'compat' => ['core' => '^0.1'],
    ], $overrides);
}

function guardRow(bool $enabled = true): ExtensionRow
{
    return ExtensionRow::query()->create([
        ExtensionSchema::SLUG => 'baxela/guard-test',
        ExtensionSchema::ENABLED => $enabled,
    ]);
}

it('boots an enabled, compatible extension and registers its driver', function () {
    guardRow();

    guardExtensionProvider(guardManifest())->boot();

    $manager = app(ExtensionManager::class);

    expect(config('payment.drivers.guard-test'))->toBe('GuardTestDriver')
        ->and($manager->skipReason('baxela/guard-test'))->toBeNull();
});

it('treats an extension without a row as disabled, never broken', function () {
    guardExtensionProvider(guardManifest())->boot();

    $manager = app(ExtensionManager::class);

    expect(config('payment.drivers.guard-test'))->toBeNull()
        ->and($manager->skipReason('baxela/guard-test'))->toBe('missing_row');
});

it('skips a disabled extension without touching the registry', function () {
    guardRow(enabled: false);

    guardExtensionProvider(guardManifest())->boot();

    $manager = app(ExtensionManager::class);

    expect(config('payment.drivers.guard-test'))->toBeNull()
        ->and($manager->skipReason('baxela/guard-test'))->toBe('disabled');
});

it('skips an incompatible extension and names the required range', function () {
    guardRow();

    guardExtensionProvider(guardManifest(['compat' => ['core' => '^9.0']]))->boot();

    $manager = app(ExtensionManager::class);

    expect(config('payment.drivers.guard-test'))->toBeNull()
        ->and($manager->skipReason('baxela/guard-test'))->toContain('compat_mismatch')
        ->and($manager->skipReason('baxela/guard-test'))->toContain('^9.0');
});

it('keeps a skipped extension inert at the payment webhook', function () {
    guardRow(enabled: false);

    guardExtensionProvider(guardManifest())->boot();

    // The driver never reached the registry, so the webhook answers the
    // standard unknown-driver error — present but inert, never fatal.
    $this->postJson('api/v1/payment/webhook/guard-test')
        ->assertStatus(400)
        ->assertJsonPath('code', 'payment.process.method_not_supported');
});
