<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\Models\Permission;
use Modules\Auth\Models\User;
use Modules\Auth\Schemas\GuardsEnum;
use Modules\Core\Extensions\ExtensionManager;
use Modules\Core\Models\Extension as ExtensionRow;
use Modules\Core\Schemas\Extension\ExtensionSchema;
use Modules\Core\Tests\Feature\HelperTrait;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

beforeEach(function () {
    ExtensionManager::flush();
});

it('denies a guest with 401', function () {
    $this->getJson($this->adminUrl('/extensions'))->assertUnauthorized();
});

it('requires the extensions permission', function () {
    $this->actingAs(User::factory()->active()->create());

    $this->getJson($this->adminUrl('/extensions'))->assertForbidden();
});

it('grants the list permission on its own', function () {
    $user = User::factory()->active()->create();
    $user->givePermissionTo(
        Permission::query()->firstOrCreate([
            'name' => 'core.admin.extensions.list',
            'guard_name' => GuardsEnum::WEB->value,
        ])
    );
    $this->actingAs($user);

    $this->getJson($this->adminUrl('/extensions'))->assertOk();
});

it('lists extensions with live composer facts and skip reasons', function () {
    ExtensionRow::query()->create([
        ExtensionSchema::SLUG => 'acme/baxela-sandbox',
        ExtensionSchema::ENABLED => true,
    ]);

    $this->actingAs($this->superAdminUser());

    $response = $this->getJson($this->adminUrl('/extensions'))->assertOk();

    // Live vendor facts: no package present, so installed=false, the
    // version is null and the derived skip reason says so.
    expect($response->json('data.0.slug'))->toBe('acme/baxela-sandbox')
        ->and($response->json('data.0.enabled'))->toBeTrue()
        ->and($response->json('data.0.installed'))->toBeFalse()
        ->and($response->json('data.0.version'))->toBeNull()
        ->and($response->json('data.0.skip_reason'))->toBe('not_installed');
});
