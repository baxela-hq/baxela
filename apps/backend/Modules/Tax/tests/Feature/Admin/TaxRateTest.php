<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tax\Models\TaxClass;
use Modules\Tax\Schemas\TaxClass\TaxClassSchema;
use Modules\Tax\Schemas\TaxRate\TaxRateSchema;
use Modules\Tax\Tests\Feature\HelperTrait;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

function ratePayload(TaxClass $class, array $overrides = []): array
{
    return array_merge([
        TaxRateSchema::NAME => 'US Standard',
        TaxRateSchema::COUNTRY_CODE => 'US',
        TaxRateSchema::TAX_CLASS_ID => $class->id,
        TaxRateSchema::RATE => '8.5',
        TaxRateSchema::IS_ACTIVE => true,
    ], $overrides);
}

it('lists, creates, shows, updates and deletes tax rates', function () {
    $this->actingAs($this->superAdminUser());
    $this->seedCountries('US');

    $class = TaxClass::query()->create([
        TaxClassSchema::NAME => 'Standard',
        TaxClassSchema::IS_DEFAULT => true,
        TaxClassSchema::IS_SHIPPING => false,
    ]);

    $created = $this->postJson($this->baseUrl('/admin/tax-rates'), ratePayload($class))
        ->assertCreated()
        ->assertJsonPath('data.rate', '8.5000')
        ->assertJsonPath('data.tax_class.id', $class->id);

    $id = $created->json('data.id');

    $this->getJson($this->baseUrl('/admin/tax-rates'))->assertOk()->assertJsonCount(1, 'data');

    $this->getJson($this->baseUrl("/admin/tax-rates/{$id}"))
        ->assertOk()
        ->assertJsonPath('data.id', $id);

    $this->patchJson($this->baseUrl("/admin/tax-rates/{$id}"), ratePayload($class, [
        TaxRateSchema::RATE => '5.125',
    ]))->assertOk()->assertJsonPath('data.rate', '5.1250');

    $this->deleteJson($this->baseUrl("/admin/tax-rates/{$id}"))->assertNoContent();
});

it('enforces one rate per country and class', function () {
    $this->actingAs($this->superAdminUser());
    $this->seedCountries('US', 'DE');

    $class = TaxClass::query()->create([
        TaxClassSchema::NAME => 'Standard',
        TaxClassSchema::IS_DEFAULT => true,
        TaxClassSchema::IS_SHIPPING => false,
    ]);

    $this->postJson($this->baseUrl('/admin/tax-rates'), ratePayload($class))->assertCreated();

    $this->postJson($this->baseUrl('/admin/tax-rates'), ratePayload($class, [
        TaxRateSchema::NAME => 'Duplicate',
    ]))->assertStatus(422)->assertJsonPath('code', 'http.422');

    // same class, another country is fine
    $this->postJson($this->baseUrl('/admin/tax-rates'), ratePayload($class, [
        TaxRateSchema::COUNTRY_CODE => 'DE',
    ]))->assertCreated();
});

it('rejects malformed or out-of-range rates', function () {
    $this->actingAs($this->superAdminUser());

    $class = TaxClass::query()->create([
        TaxClassSchema::NAME => 'Standard',
        TaxClassSchema::IS_DEFAULT => true,
        TaxClassSchema::IS_SHIPPING => false,
    ]);

    // beyond 100% and more than 4 fraction digits
    foreach (['100.5', '8.12345', '-1'] as $rate) {
        $this->postJson($this->baseUrl('/admin/tax-rates'), ratePayload($class, [
            TaxRateSchema::RATE => $rate,
        ]))->assertStatus(422);
    }
});

it('denies the tax-rate endpoints without permission', function () {
    $this->actingAs(\Modules\Auth\Models\User::factory()->create());

    $this->getJson($this->baseUrl('/admin/tax-rates'))->assertStatus(403);
});
