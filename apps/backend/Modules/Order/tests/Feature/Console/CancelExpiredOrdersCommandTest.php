<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Models\Variant;
use Modules\Inventory\Models\InventoryStock;
use Modules\Order\Models\Order;
use Modules\Order\Models\OrderItem;
use Modules\Order\Schemas\Order\OrderPaymentStatusEnum;
use Modules\Order\Schemas\Order\OrderSchema;
use Modules\Order\Schemas\Order\OrderStatusEnum;
use Modules\Order\Tests\Feature\HelperTrait;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

function expiredOrder(int $userId): Order
{
    return Order::factory()->create([
        OrderSchema::USER_ID => $userId,
        OrderSchema::STATUS => OrderStatusEnum::PENDING,
        OrderSchema::PAYMENT_STATUS => OrderPaymentStatusEnum::UNPAID,
        OrderSchema::EXPIRES_AT => now()->subMinute(),
    ]);
}

it('cancels expired pending-unpaid orders and releases their stock', function () {
    $user = \Modules\Auth\Models\User::factory()->create();
    $product = Product::factory()->create();
    $variant = Variant::factory()->ofProduct($product)->create();
    InventoryStock::factory()->ofVariant($variant, 2)->create();

    $expired = expiredOrder($user->id);
    $expired->items()->create([
        'variant_id' => $variant->id,
        'price_snapshot' => 100,
        'product_name_snapshot' => 'Test Product',
        'quantity' => 3,
    ]);

    $this->artisan('order:cancel-expired')->assertSuccessful();

    expect($expired->refresh()->{OrderSchema::STATUS})->toBe(OrderStatusEnum::CANCELLED)
        ->and((int) InventoryStock::query()->where('variant_id', $variant->id)->value('quantity'))->toBe(5);
});

it('leaves fresh orders and settled expired orders untouched', function () {
    $user = \Modules\Auth\Models\User::factory()->create();

    $fresh = Order::factory()->create([
        OrderSchema::USER_ID => $user->id,
        OrderSchema::STATUS => OrderStatusEnum::PENDING,
        OrderSchema::PAYMENT_STATUS => OrderPaymentStatusEnum::UNPAID,
        OrderSchema::EXPIRES_AT => now()->addMinutes(25),
    ]);

    // Expired, but already paid — the reservation is settled, not abandoned.
    $paid = Order::factory()->create([
        OrderSchema::USER_ID => $user->id,
        OrderSchema::STATUS => OrderStatusEnum::PROCESSING,
        OrderSchema::PAYMENT_STATUS => OrderPaymentStatusEnum::PAID,
        OrderSchema::EXPIRES_AT => now()->subMinute(),
    ]);

    $this->artisan('order:cancel-expired')->assertSuccessful();

    expect($fresh->refresh()->{OrderSchema::STATUS})->toBe(OrderStatusEnum::PENDING)
        ->and($paid->refresh()->{OrderSchema::STATUS})->toBe(OrderStatusEnum::PROCESSING);
});
