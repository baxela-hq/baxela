<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Transfer\Tests\Feature\HelperTrait;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

it('exports a module in dependency order with request-shaped rows', function () {
    $this->seedCountries('DE');
    $seeded = $this->seedTransferTax();
    $this->actingAs($this->superAdminUser());

    $file = json_decode(
        $this->getJson($this->baseUrl('/admin/exports/tax'))->assertOk()->streamedContent(),
        true
    );

    expect($file['format'])->toBe('baxela.module-export')
        ->and($file['module'])->toBe('tax')
        ->and($file['version'])->toBe(1)
        ->and(array_column($file['sections'], 'entity'))->toBe(['tax-classes', 'tax-rates'])
        ->and($file['sections'][0]['rows'])->toHaveCount(2)
        // The rate references its class by the exporting store's source id.
        ->and($file['sections'][1]['rows'][0]['source_id'])->toBe($seeded['rate']->id)
        ->and($file['sections'][1]['rows'][0]['payload']['tax_class_id'])->toBe($seeded['default']->id)
        ->and($file['sections'][1]['rows'][0]['payload']['rate'])->toBe('19.0000');
});

it('downloads the export as a json attachment', function () {
    $this->seedCountries('DE');
    $this->seedTransferTax();
    $this->actingAs($this->superAdminUser());

    $response = $this->getJson($this->baseUrl('/admin/exports/tax'));

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toBe('application/json; charset=UTF-8')
        ->and($response->headers->get('Content-Disposition'))->toContain('attachment; filename=transfer-tax-')
        ->and($response->headers->get('Content-Disposition'))->toContain('.json');
});

it('rejects the export of a module that did not opt in', function () {
    $this->actingAs($this->superAdminUser());

    $this->getJson($this->baseUrl('/admin/exports/order'))
        ->assertStatus(404)
        ->assertJsonPath('code', 'transfer.export.module_unknown');
});

it('denies export to guests', function () {
    $this->seedCountries('DE');
    $this->seedTransferTax();

    $this->getJson($this->baseUrl('/admin/exports/tax'))->assertStatus(401);
});
