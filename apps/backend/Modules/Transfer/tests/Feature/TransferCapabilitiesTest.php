<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\Models\User;
use Modules\Transfer\Tests\Feature\HelperTrait;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

it('denies capabilities to guests', function () {
    $this->getJson($this->baseUrl('/admin/capabilities'))->assertStatus(401);
});

it('denies capabilities without permissions', function () {
    $this->actingAs(User::factory()->create(['is_active' => true]));

    $this->getJson($this->baseUrl('/admin/capabilities'))
        ->assertStatus(403)
        ->assertJsonPath('code', 'http.403');
});

it('lists every enabled module with its transfer support', function () {
    $this->actingAs($this->superAdminUser());

    $modules = collect(
        $this->getJson($this->baseUrl('/admin/capabilities'))
            ->assertOk()
            ->json('data')
    )->pluck('supported', 'module');

    // The Transfer module is only meaningful with at least one
    // participant; Tax is the reference implementation.
    expect($modules['tax'])->toBeTrue()
        ->and($modules['transfer'])->toBeFalse()
        ->and($modules['order'])->toBeFalse();
});

it('declares the participating module sections with their flags', function () {
    $this->actingAs($this->superAdminUser());

    $tax = collect(
        $this->getJson($this->baseUrl('/admin/capabilities'))
            ->assertOk()
            ->json('data')
    )->firstWhere('module', 'tax');

    expect($tax['supported'])->toBeTrue()
        ->and($tax['sections'])->toHaveCount(2)
        ->and($tax['sections'][0]['key'])->toBe('tax-classes')
        ->and($tax['sections'][0]['exportable'])->toBeTrue()
        ->and($tax['sections'][0]['importable'])->toBeTrue()
        ->and($tax['sections'][1]['key'])->toBe('tax-rates');
});

it('publishes the wired module data endpoints and none for the others', function () {
    $this->actingAs($this->superAdminUser());

    $modules = collect(
        $this->getJson($this->baseUrl('/admin/capabilities'))
            ->assertOk()
            ->json('data')
    );

    $tax = $modules->firstWhere('module', 'tax');
    expect($tax['endpoints'])->toBe([
        'export' => 'tax/admin/data/export',
        'import_preview' => 'tax/admin/data/import/preview',
        'import' => 'tax/admin/data/import',
        'imports' => 'tax/admin/data/imports',
    ]);

    // Every wired wave-1 module publishes the full surface...
    foreach (['setting', 'shipping', 'discount', 'contact', 'menu'] as $name) {
        expect($modules->firstWhere('module', $name)['endpoints'])->not->toBeNull();
    }

    // ...and modules that did not opt in publish none.
    expect($modules->firstWhere('module', 'order')['endpoints'])->toBeNull()
        ->and($modules->firstWhere('module', 'transfer')['endpoints'])->toBeNull();
});
