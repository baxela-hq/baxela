<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\Models\User;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Schemas\Product\ProductSchema;
use Modules\Tax\Models\TaxClass;
use Modules\Tax\Schemas\TaxClass\TaxClassSchema;
use Modules\Tax\Tests\Feature\HelperTrait;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

function classPayload(array $overrides = []): array
{
    return array_merge([
        TaxClassSchema::NAME => 'Standard',
        TaxClassSchema::IS_DEFAULT => true,
        TaxClassSchema::IS_SHIPPING => false,
    ], $overrides);
}

it('lists, creates, shows, updates and deletes tax classes', function () {
    $this->actingAs($this->superAdminUser());

    // a default keeps the platform configured; the CRUD target is a plain
    // class (the active default is deliberately undeletable)
    $this->postJson($this->baseUrl('/admin/tax-classes'), classPayload())->assertCreated();

    $created = $this->postJson($this->baseUrl('/admin/tax-classes'), classPayload([
        TaxClassSchema::NAME => 'Reduced',
        TaxClassSchema::IS_DEFAULT => false,
    ]))->assertCreated()
        ->assertJsonPath('data.name', 'Reduced')
        ->assertJsonPath('data.is_default', false);

    $id = $created->json('data.id');

    $this->getJson($this->baseUrl('/admin/tax-classes'))
        ->assertOk()
        ->assertJsonCount(2, 'data');

    $this->getJson($this->baseUrl("/admin/tax-classes/{$id}"))
        ->assertOk()
        ->assertJsonPath('data.id', $id);

    $this->patchJson($this->baseUrl("/admin/tax-classes/{$id}"), classPayload([
        TaxClassSchema::NAME => 'Reduced (updated)',
        TaxClassSchema::IS_DEFAULT => false,
    ]))->assertOk()->assertJsonPath('data.name', 'Reduced (updated)');

    $this->deleteJson($this->baseUrl("/admin/tax-classes/{$id}"))->assertNoContent();
    expect(TaxClass::count())->toBe(1);
});

it('promoting a new default demotes the previous one transactionally', function () {
    $this->actingAs($this->superAdminUser());

    $first = $this->postJson($this->baseUrl('/admin/tax-classes'), classPayload())->json('data.id');

    $this->postJson($this->baseUrl('/admin/tax-classes'), classPayload([
        TaxClassSchema::NAME => 'New default',
        TaxClassSchema::IS_DEFAULT => true,
    ]))->assertCreated();

    expect(TaxClass::query()->where(TaxClassSchema::IS_DEFAULT, true)->count())->toBe(1)
        ->and(TaxClass::query()->find($first)->{TaxClassSchema::IS_DEFAULT})->toBeFalse();
});

it('promoting a new shipping class unflags the previous one', function () {
    $this->actingAs($this->superAdminUser());

    $first = $this->postJson($this->baseUrl('/admin/tax-classes'), classPayload([
        TaxClassSchema::IS_DEFAULT => false,
        TaxClassSchema::IS_SHIPPING => true,
    ]))->json('data.id');

    $this->postJson($this->baseUrl('/admin/tax-classes'), classPayload([
        TaxClassSchema::NAME => 'Shipping 2',
        TaxClassSchema::IS_DEFAULT => false,
        TaxClassSchema::IS_SHIPPING => true,
    ]))->assertCreated();

    expect(TaxClass::query()->where(TaxClassSchema::IS_SHIPPING, true)->count())->toBe(1)
        ->and(TaxClass::query()->find($first)->{TaxClassSchema::IS_SHIPPING})->toBeFalse();
});

it('refuses deleting the active default class', function () {
    $this->actingAs($this->superAdminUser());

    $id = $this->postJson($this->baseUrl('/admin/tax-classes'), classPayload())->json('data.id');

    $this->deleteJson($this->baseUrl("/admin/tax-classes/{$id}"))
        ->assertStatus(400)
        ->assertJsonPath('code', 'tax.class.delete_default');

    expect(TaxClass::count())->toBe(1);
});

it('refuses deleting a class still referenced by products', function () {
    $this->actingAs($this->superAdminUser());

    // the default keeps checkout alive; the victim is a non-default class
    $this->postJson($this->baseUrl('/admin/tax-classes'), classPayload());

    $id = $this->postJson($this->baseUrl('/admin/tax-classes'), classPayload([
        TaxClassSchema::NAME => 'Reduced',
        TaxClassSchema::IS_DEFAULT => false,
    ]))->json('data.id');

    Product::factory()->create([ProductSchema::TAX_CLASS_ID => $id]);

    $this->deleteJson($this->baseUrl("/admin/tax-classes/{$id}"))
        ->assertStatus(400)
        ->assertJsonPath('code', 'tax.class.in_use');

    expect(TaxClass::count())->toBe(2);
});

it('denies the tax-class endpoints without permission', function () {
    $this->actingAs(User::factory()->create());

    $this->getJson($this->baseUrl('/admin/tax-classes'))->assertStatus(403);
});
