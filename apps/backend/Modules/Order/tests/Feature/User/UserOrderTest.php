<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\Models\User;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Models\Variant;
use Modules\Inventory\Models\InventoryStock;
use Modules\Order\Models\Order;
use Modules\Order\Schemas\Order\OrderPaymentStatusEnum;
use Modules\Order\Schemas\Order\OrderSchema;
use Modules\Order\Schemas\Order\OrderStatusEnum;
use Modules\Order\Schemas\OrderItem\OrderItemSchema;
use Modules\Order\Tests\Feature\HelperTrait;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

function orderFor(User $user, OrderStatusEnum $status = OrderStatusEnum::PENDING): Order
{
    return Order::factory()->create([
        OrderSchema::USER_ID => $user->id,
        OrderSchema::STATUS => $status,
        OrderSchema::PAYMENT_STATUS => OrderPaymentStatusEnum::UNPAID,
        OrderSchema::EXPIRES_AT => now()->addHour(),
    ]);
}

it('lists only the signed-in user\'s orders', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $orderA = orderFor($userA);
    orderFor($userB);

    $this->actingAs($userA)
        ->getJson($this->baseUrl('/user/orders'))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.'.OrderSchema::ORDER_CODE, $orderA->{OrderSchema::ORDER_CODE});
});

it('shows an order by its code', function () {
    $user = User::factory()->create();
    $order = orderFor($user);

    $this->actingAs($user)
        ->getJson($this->baseUrl('/user/orders/'.$order->{OrderSchema::ORDER_CODE}))
        ->assertOk()
        ->assertJsonPath('data.'.OrderSchema::ORDER_CODE, $order->{OrderSchema::ORDER_CODE})
        ->assertJsonPath('data.'.OrderSchema::STATUS, OrderStatusEnum::PENDING->value);
});

it('returns 404 for another user\'s order code', function () {
    $user = User::factory()->create();
    $order = orderFor(User::factory()->create());

    $this->actingAs($user)
        ->getJson($this->baseUrl('/user/orders/'.$order->{OrderSchema::ORDER_CODE}))
        ->assertStatus(404)
        ->assertJsonPath('code', 'http.404');
});

it('cancels a pending order and restores its reserved stock', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create();
    $variant = Variant::factory()->ofProduct($product)->create();
    InventoryStock::factory()->ofVariant($variant, 5)->create();

    $order = orderFor($user);
    $order->items()->create([
        OrderItemSchema::VARIANT_ID => $variant->id,
        OrderItemSchema::PRICE_SNAPSHOT => 100,
        OrderItemSchema::PRODUCT_NAME_SNAPSHOT => 'Test Product',
        OrderItemSchema::QUANTITY => 3,
    ]);

    $this->actingAs($user)
        ->patchJson($this->baseUrl('/user/orders/'.$order->{OrderSchema::ORDER_CODE}.'/cancel'))
        ->assertOk();

    expect($order->refresh()->{OrderSchema::STATUS})->toBe(OrderStatusEnum::CANCELLED)
        ->and((int) InventoryStock::query()->where('variant_id', $variant->id)->value('quantity'))->toBe(8);
});

it('rejects cancelling orders already in fulfilment', function (OrderStatusEnum $status) {
    $user = User::factory()->create();
    $order = orderFor($user, $status);

    $this->actingAs($user)
        ->patchJson($this->baseUrl('/user/orders/'.$order->{OrderSchema::ORDER_CODE}.'/cancel'))
        ->assertStatus(422)
        ->assertJsonPath('code', 'http.422');

    expect($order->refresh()->{OrderSchema::STATUS})->toBe($status);
})->with([
    'shipped' => [OrderStatusEnum::SHIPPED],
    'completed' => [OrderStatusEnum::COMPLETED],
    'already cancelled' => [OrderStatusEnum::CANCELLED],
]);
