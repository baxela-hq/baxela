<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Models\Variant;
use Modules\Core\Contracts\Events\Inventory\StockDepletedEvent;
use Modules\Core\Contracts\Events\Inventory\StockIncreasedEvent;
use Modules\Inventory\Gateways\InventoryGateway;
use Modules\Inventory\Models\InventoryStock;
use Modules\Inventory\Tests\Feature\HelperTrait;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

function stockedVariant(int $quantity): Variant
{
    $product = Product::factory()->create();
    $variant = Variant::factory()->ofProduct($product)->create();
    InventoryStock::factory()->ofVariant($variant, $quantity)->create();

    return $variant;
}

function quantityOf(Variant $variant): int
{
    return (int) InventoryStock::query()->where('variant_id', $variant->id)->value('quantity');
}

it('decrements stock atomically and reports availability', function () {
    $gateway = app(InventoryGateway::class);
    $variant = stockedVariant(5);

    expect($gateway->checkAvailability((string) $variant->id, 5))->toBeTrue()
        ->and($gateway->checkAvailability((string) $variant->id, 6))->toBeFalse()
        ->and($gateway->availableQuantity((string) $variant->id))->toBe(5)
        ->and($gateway->availableQuantity('999999'))->toBeNull()
        ->and($gateway->decrement((string) $variant->id, 3))->toBeTrue()
        ->and(quantityOf($variant))->toBe(2);
});

it('refuses to decrement below zero and leaves the quantity untouched', function () {
    $variant = stockedVariant(3);

    expect(app(InventoryGateway::class)->decrement((string) $variant->id, 4))->toBeFalse()
        ->and(quantityOf($variant))->toBe(3);
});

it('fires the depleted event when a decrement drains the last unit', function () {
    $variant = stockedVariant(2);

    Event::fakeFor(function () use ($variant): void {
        expect(app(InventoryGateway::class)->decrement((string) $variant->id, 2))->toBeTrue();
        Event::assertDispatched(StockDepletedEvent::class, 1);
    });

    expect(quantityOf($variant))->toBe(0);
});

it('restores stock and announces the increase', function () {
    $variant = stockedVariant(2);

    Event::fakeFor(function () use ($variant): void {
        app(InventoryGateway::class)->restore((string) $variant->id, 3);
        Event::assertDispatched(StockIncreasedEvent::class, 1);
    });

    expect(quantityOf($variant))->toBe(5);
});

it('upserts stock rows on insert and update', function () {
    $gateway = app(InventoryGateway::class);
    $variant = stockedVariant(5);

    $gateway->upsertStock($variant->id, 10);
    expect(quantityOf($variant))->toBe(10);

    $fresh = Variant::factory()->ofProduct(Product::factory()->create())->create();
    $gateway->upsertStock($fresh->id, 7);
    expect(quantityOf($fresh))->toBe(7)
        ->and(InventoryStock::query()->where('variant_id', $fresh->id)->count())->toBe(1);
});
