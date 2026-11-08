<?php

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Contracts\Gateways\Tax\DTOs\TaxCalculationInput;
use Modules\Core\Contracts\Gateways\Tax\DTOs\TaxCalculationItem;
use Modules\Core\Database\Seeders\CoreDatabaseSeeder;
use Modules\Core\Exceptions\Tax\TaxConfigurationException;
use Modules\Core\Models\Region;
use Modules\Tax\Gateways\TaxGateway;
use Modules\Tax\Schemas\TaxRate\TaxRateSchema;
use Modules\Tax\Tests\Feature\HelperTrait;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

function regionInput(string $country, string $region, int $amount = 10000): TaxCalculationInput
{
    return new TaxCalculationInput(
        items: [new TaxCalculationItem(variant_id: 1, amount_minor: $amount)],
        country_code: $country,
        region_code: $region,
    );
}

it('rates with the region-specific rate when present, else the country-wide rate', function () {
    $class = $this->taxClass(isDefault: true);
    $this->taxRate($class, 'US', '8.0000', region: '');       // country-wide
    $this->taxRate($class, 'US', '10.0000', region: 'US-CA'); // California

    $gateway = app(TaxGateway::class);

    // Region selected + regional rate exists → the regional rate.
    $california = $gateway->calculateForItems(regionInput('US', 'US-CA'));
    expect($california->lines[1]->rate)->toBe('10.0000');

    // Region selected + regional rate absent → the country rate.
    $newYork = $gateway->calculateForItems(regionInput('US', 'US-NY'));
    expect($newYork->lines[1]->rate)->toBe('8.0000');

    // No region on the input → LOUD failure: the country has regional
    // rates configured, so an address without a region cannot be rated
    // (gap-C1a D3 rule 2 — the dedicated test below asserts the throw).
    expect(fn () => $gateway->calculateForItems(regionInput('US', '')))
        ->toThrow(TaxConfigurationException::class);
});

it('fails loudly when the country has regional rates but the address lacks a region', function () {
    $class = $this->taxClass(isDefault: true);
    // ONLY a regional rate for the US — the California hole.
    $this->taxRate($class, 'US', '10.0000', region: 'US-CA');

    $this->expectException(TaxConfigurationException::class);
    app(TaxGateway::class)->calculateForItems(regionInput('US', ''));
});

it('keeps a country with only country-wide rates zero-configuration valid without a region', function () {
    $class = $this->taxClass(isDefault: true);
    $this->taxRate($class, 'DE', '19.0000', region: '');

    $input = new TaxCalculationInput(
        items: [new TaxCalculationItem(variant_id: 1, amount_minor: 10000)],
        country_code: 'DE',
        region_code: '',
    );

    expect(app(TaxGateway::class)->calculateForItems($input)->lines[1]->rate)->toBe('19.0000');
});

it('falls back to the country rate when the regional rate is inactive (deactivation rule)', function () {
    $class = $this->taxClass(isDefault: true);
    $this->taxRate($class, 'US', '8.0000', region: '');
    $this->taxRate($class, 'US', '0.0000', isActive: false, region: 'US-CA');

    $result = app(TaxGateway::class)->calculateForItems(regionInput('US', 'US-CA'));

    // The inactive regional rate is removed from consideration — the
    // country rate applies again (gap-C1a D2).
    expect($result->lines[1]->rate)->toBe('8.0000');
});

it('keeps a missing country configuration a valid zero (unchanged semantics)', function () {
    $class = $this->taxClass(isDefault: true);

    $input = new TaxCalculationInput(
        items: [new TaxCalculationItem(variant_id: 1, amount_minor: 10000)],
        country_code: 'FR',
        region_code: '',
    );

    expect(app(TaxGateway::class)->calculateForItems($input)->lines[1]->tax_minor)->toBe(0);
});

it('rejects a duplicate country-wide rate at the database level (scope key)', function () {
    $class = $this->taxClass(isDefault: true);
    $this->taxRate($class, 'US', '8.0000', region: '');

    expect(fn () => $this->taxRate($class, 'US', '9.0000', region: ''))
        ->toThrow(UniqueConstraintViolationException::class);
});

it('exposes the region on the rate resource as null for the country-wide scope', function () {
    $this->actingAs($this->superAdminUser());
    $this->seedCountries('US');
    $this->seedRegion('US', 'US-CA');
    $class = $this->taxClass(isDefault: true);

    $countryWide = $this->postJson($this->baseUrl('/admin/tax-rates'), [
        TaxRateSchema::NAME => 'US default',
        TaxRateSchema::COUNTRY_CODE => 'US',
        TaxRateSchema::TAX_CLASS_ID => $class->id,
        TaxRateSchema::RATE => '8.5',
        TaxRateSchema::IS_ACTIVE => true,
    ])->assertCreated();

    expect($countryWide->json('data.'.TaxRateSchema::REGION_CODE))->toBeNull();

    $regional = $this->postJson($this->baseUrl('/admin/tax-rates'), [
        TaxRateSchema::NAME => 'CA',
        TaxRateSchema::COUNTRY_CODE => 'US',
        TaxRateSchema::REGION_CODE => 'us-ca', // normalized on write
        TaxRateSchema::TAX_CLASS_ID => $class->id,
        TaxRateSchema::RATE => '10',
        TaxRateSchema::IS_ACTIVE => true,
    ])->assertCreated();

    expect($regional->json('data.'.TaxRateSchema::REGION_CODE))->toBe('US-CA');

    // A duplicate regional rate 422s at validation.
    $this->postJson($this->baseUrl('/admin/tax-rates'), [
        TaxRateSchema::NAME => 'CA dup',
        TaxRateSchema::COUNTRY_CODE => 'US',
        TaxRateSchema::REGION_CODE => 'US-CA',
        TaxRateSchema::TAX_CLASS_ID => $class->id,
        TaxRateSchema::RATE => '11',
        TaxRateSchema::IS_ACTIVE => true,
    ])->assertStatus(422);

    // A duplicate country-wide rate 422s at validation (the implicit rule).
    $this->postJson($this->baseUrl('/admin/tax-rates'), [
        TaxRateSchema::NAME => 'US dup',
        TaxRateSchema::COUNTRY_CODE => 'US',
        TaxRateSchema::TAX_CLASS_ID => $class->id,
        TaxRateSchema::RATE => '9',
        TaxRateSchema::IS_ACTIVE => true,
    ])->assertStatus(422);
});

it('rejects a region that does not belong to the submitted country', function () {
    $this->actingAs($this->superAdminUser());
    $this->seedCountries('US', 'DE');
    $this->seedRegion('US', 'US-CA');
    $class = $this->taxClass(isDefault: true);

    // DE + US-CA is not a valid pair.
    $this->postJson($this->baseUrl('/admin/tax-rates'), [
        TaxRateSchema::NAME => 'bad pair',
        TaxRateSchema::COUNTRY_CODE => 'DE',
        TaxRateSchema::REGION_CODE => 'US-CA',
        TaxRateSchema::TAX_CLASS_ID => $class->id,
        TaxRateSchema::RATE => '19',
        TaxRateSchema::IS_ACTIVE => true,
    ])->assertStatus(422);
});

it("lists a country's seeded regions through the public endpoint", function () {
    Region::query()->create(['country_code' => 'US', 'code' => 'US-CA', 'name' => 'California']);
    Region::query()->create(['country_code' => 'US', 'code' => 'US-NY', 'name' => 'New York']);
    Region::query()->create(['country_code' => 'DE', 'code' => 'DE-BY', 'name' => 'Bayern']);

    $response = $this->getJson('api/v1/core/public/regions?country_code=US')->assertOk();

    $codes = collect($response->json('data'))->pluck('code');

    expect($codes->all())->toEqualCanonicalizing(['US-CA', 'US-NY'])
        ->and($codes->all())->not->toContain('DE-BY');
});

it('seeds the US/CA/DE region sets through the core seeder', function () {
    $this->seed(CoreDatabaseSeeder::class);

    expect(Region::query()->where('country_code', 'US')->count())->toBe(51)
        ->and(Region::query()->where('country_code', 'CA')->count())->toBe(13)
        ->and(Region::query()->where('country_code', 'DE')->count())->toBe(16);
});
