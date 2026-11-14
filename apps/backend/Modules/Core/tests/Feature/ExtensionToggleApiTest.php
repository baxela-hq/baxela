<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Modules\Auth\Models\Permission;
use Modules\Auth\Models\User;
use Modules\Auth\Schemas\GuardsEnum;
use Modules\Core\Actions\Admin\Extension\ToggleExtensionAction;
use Modules\Core\Extensions\ComposerPackages;
use Modules\Core\Extensions\ExtensionManager;
use Modules\Core\Models\Extension as ExtensionRow;
use Modules\Core\Schemas\Extension\ExtensionSchema;
use Modules\Core\Tests\Feature\HelperTrait;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

const SLUG = 'acme/baxela-sandbox';

beforeEach(function () {
    ExtensionManager::flush();
});

/**
 * @param  array<string, mixed>|null  $package  the record ComposerPackages::package() reports for SLUG
 */
function mockComposerPackages(?array $package): void
{
    test()->mock(ComposerPackages::class, function (MockInterface $mock) use ($package) {
        $mock->shouldReceive('package')->with(SLUG)->andReturn($package);
        $mock->shouldReceive('version')->with(SLUG)->andReturn($package === null ? null : '0.0.1');
    });
}

function grantAndActAs(string $permission): void
{
    $user = User::factory()->active()->create();
    $user->givePermissionTo(
        Permission::query()->firstOrCreate([
            'name' => $permission,
            'guard_name' => GuardsEnum::WEB->value,
        ])
    );
    test()->actingAs($user);
}

function extensionRow(bool $enabled): void
{
    ExtensionRow::query()->create([
        ExtensionSchema::SLUG => SLUG,
        ExtensionSchema::ENABLED => $enabled,
    ]);
}

it('rejects guests on the toggle endpoints', function () {
    $this->postJson($this->adminUrl('/extensions/acme/baxela-sandbox/enable'))->assertUnauthorized();
    $this->postJson($this->adminUrl('/extensions/acme/baxela-sandbox/disable'))->assertUnauthorized();
});

it('forbids authenticated users without a toggle permission', function () {
    $this->actingAs(User::factory()->active()->create());

    $this->postJson($this->adminUrl('/extensions/acme/baxela-sandbox/enable'))->assertForbidden();
    $this->postJson($this->adminUrl('/extensions/acme/baxela-sandbox/disable'))->assertForbidden();
});

it('enforces enable and disable permissions independently', function () {
    grantAndActAs('core.admin.extensions.enable');

    extensionRow(enabled: true);

    // Holding only "enable" must not buy "disable"…
    $this->postJson($this->adminUrl('/extensions/acme/baxela-sandbox/disable'))->assertForbidden();

    // …and holding only "disable" must not buy "enable".
    grantAndActAs('core.admin.extensions.disable');
    $this->postJson($this->adminUrl('/extensions/acme/baxela-sandbox/enable'))->assertForbidden();
});

it('enables a disabled, installed extension and derives fresh state', function () {
    extensionRow(enabled: false);
    mockComposerPackages([
        'name' => SLUG,
        'type' => 'baxela-extension',
        'extra' => ['baxela' => ['slug' => SLUG, 'compat' => ['core' => '^0.1']]],
    ]);

    $this->actingAs($this->superAdminUser());

    $this->postJson($this->adminUrl('/extensions/acme/baxela-sandbox/enable'))
        ->assertOk()
        ->assertJsonPath('data.slug', SLUG)
        ->assertJsonPath('data.enabled', true)
        ->assertJsonPath('data.installed', true)
        ->assertJsonPath('data.version', '0.0.1')
        ->assertJsonPath('data.skip_reason', null);

    expect(ExtensionRow::query()->where(ExtensionSchema::SLUG, SLUG)->first()->{ExtensionSchema::ENABLED})->toBeTrue();
});

it('never reports a stale boot diagnostic after enabling', function () {
    extensionRow(enabled: false);
    mockComposerPackages([
        'name' => SLUG,
        'type' => 'baxela-extension',
        'extra' => ['baxela' => ['slug' => SLUG, 'compat' => ['core' => '^0.1']]],
    ]);

    // The boot guard runs against the disabled row and records "disabled".
    $manager = app(ExtensionManager::class);
    $manager->shouldBoot(SLUG, ['slug' => SLUG, 'compat' => ['core' => '^0.1']]);
    expect($manager->skipReason(SLUG))->toBe('disabled');

    $this->actingAs($this->superAdminUser());

    // The API response must derive from current rows + Composer, not the
    // recorded diagnostic — enabled=true alongside skip_reason="disabled"
    // would be exactly the lie the builder exists to prevent.
    $this->postJson($this->adminUrl('/extensions/acme/baxela-sandbox/enable'))
        ->assertOk()
        ->assertJsonPath('data.enabled', true)
        ->assertJsonPath('data.skip_reason', null);
});

it('disables an enabled extension and derives the disabled reason', function () {
    extensionRow(enabled: true);
    mockComposerPackages([
        'name' => SLUG,
        'type' => 'baxela-extension',
        'extra' => ['baxela' => ['slug' => SLUG, 'compat' => ['core' => '^0.1']]],
    ]);

    $this->actingAs($this->superAdminUser());

    $this->postJson($this->adminUrl('/extensions/acme/baxela-sandbox/disable'))
        ->assertOk()
        ->assertJsonPath('data.enabled', false)
        ->assertJsonPath('data.installed', true)
        ->assertJsonPath('data.skip_reason', 'disabled');

    expect(ExtensionRow::query()->where(ExtensionSchema::SLUG, SLUG)->first()->{ExtensionSchema::ENABLED})->toBeFalse();
});

it('disables a leftover row whose package is gone from vendor', function () {
    extensionRow(enabled: true);
    mockComposerPackages(null);

    $this->actingAs($this->superAdminUser());

    // CLI parity: a leftover row stays disableable. The reason names the
    // most immediate blocker — the package is gone.
    $this->postJson($this->adminUrl('/extensions/acme/baxela-sandbox/disable'))
        ->assertOk()
        ->assertJsonPath('data.enabled', false)
        ->assertJsonPath('data.installed', false)
        ->assertJsonPath('data.skip_reason', 'not_installed');
});

it('refuses to enable an extension whose package is not in vendor', function () {
    extensionRow(enabled: false);
    mockComposerPackages(null);

    $this->actingAs($this->superAdminUser());

    $this->postJson($this->adminUrl('/extensions/acme/baxela-sandbox/enable'))
        ->assertStatus(422)
        ->assertJsonPath('code', 'core.extensions.not_installed');
});

it('refuses to toggle a slug with no registry row', function () {
    mockComposerPackages([
        'name' => SLUG,
        'type' => 'baxela-extension',
        'extra' => ['baxela' => ['slug' => SLUG]],
    ]);

    $this->actingAs($this->superAdminUser());

    $this->postJson($this->adminUrl('/extensions/acme/baxela-sandbox/enable'))
        ->assertStatus(422)
        ->assertJsonPath('code', 'core.extensions.not_installed');

    $this->postJson($this->adminUrl('/extensions/acme/baxela-sandbox/disable'))
        ->assertStatus(422)
        ->assertJsonPath('code', 'core.extensions.not_installed');

    expect(ExtensionRow::count())->toBe(0);
});

it('rejects malformed package names before the action runs', function () {
    // The spy proves the validation boundary, not just the database:
    // ToggleExtensionAction must never be constructed a call for a name
    // the CLI would have refused.
    $spy = $this->spy(ToggleExtensionAction::class);

    $this->actingAs($this->superAdminUser());

    $response = $this->postJson($this->adminUrl('/extensions/acme/notbaxela-foo/enable'));

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['slug']);
    // The validation envelope carries the generic http.422 code — the
    // domain error core.extensions.not_installed must NOT appear, since
    // the action never ran.
    expect($response->json('code'))->toBe('http.422');
    $spy->shouldNotHaveReceived('handle');

    expect(ExtensionRow::count())->toBe(0);
});

it('is idempotent for repeated toggles', function () {
    extensionRow(enabled: false);
    mockComposerPackages([
        'name' => SLUG,
        'type' => 'baxela-extension',
        'extra' => ['baxela' => ['slug' => SLUG, 'compat' => ['core' => '^0.1']]],
    ]);

    $this->actingAs($this->superAdminUser());

    $this->postJson($this->adminUrl('/extensions/acme/baxela-sandbox/enable'))->assertOk();
    $this->postJson($this->adminUrl('/extensions/acme/baxela-sandbox/enable'))->assertOk();
    $this->postJson($this->adminUrl('/extensions/acme/baxela-sandbox/disable'))->assertOk();
    $this->postJson($this->adminUrl('/extensions/acme/baxela-sandbox/disable'))->assertOk();

    expect(ExtensionRow::count())->toBe(1)
        ->and(ExtensionRow::query()->where(ExtensionSchema::SLUG, SLUG)->first()->{ExtensionSchema::ENABLED})->toBeFalse();
});

it('treats an absent compat constraint as incompatible without type errors', function () {
    $packages = new class extends ComposerPackages
    {
        /** @return list<array<string, mixed>> */
        protected function installed(): array
        {
            return [
                // Manifest present, constraint key absent — the old cast
                // yielded '' and incompatible(); compatConstraint mirrors it.
                ['name' => 'acme/baxela-sandbox', 'type' => 'baxela-extension', 'extra' => ['baxela' => ['slug' => 'acme/baxela-sandbox']]],
                // Whole manifest absent.
                ['name' => 'acme/baxela-nometa', 'type' => 'baxela-extension'],
            ];
        }
    };

    expect($packages->compatConstraint('acme/baxela-sandbox'))->toBe('')
        ->and($packages->compatible('acme/baxela-sandbox'))->toBeFalse()
        ->and($packages->compatConstraint('acme/baxela-nometa'))->toBeNull()
        ->and($packages->compatible('acme/baxela-nometa'))->toBeFalse()
        ->and($packages->compatConstraint('acme/baxela-ghost'))->toBeNull()
        ->and($packages->compatible('acme/baxela-ghost'))->toBeFalse();
});
