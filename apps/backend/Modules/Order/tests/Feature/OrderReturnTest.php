<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\Models\User;
use Modules\Inventory\Models\InventoryStock;
use Modules\Order\Models\Order;
use Modules\Order\Models\OrderItem;
use Modules\Order\Models\OrderReturn;
use Modules\Order\Schemas\Order\OrderPaymentStatusEnum;
use Modules\Order\Schemas\Order\OrderSchema;
use Modules\Order\Schemas\Order\OrderStatusEnum;
use Modules\Order\Schemas\OrderItem\OrderItemSchema;
use Modules\Order\Schemas\OrderReturn\OrderReturnSchema;
use Modules\Order\Schemas\OrderReturn\OrderReturnStatusEnum;
use Modules\Order\Tests\Feature\HelperTrait;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

function shippedPaidOrder(User $user, array $overrides = []): Order
{
    static $variantSeq = 70;
    $order = Order::query()->create(array_merge([
        OrderSchema::USER_ID => $user->id,
        OrderSchema::ORDER_CODE => 'RT'.uniqid(),
        OrderSchema::STATUS => OrderStatusEnum::SHIPPED,
        OrderSchema::PAYMENT_STATUS => OrderPaymentStatusEnum::PAID,
        OrderSchema::PAID_AT => now(),
        OrderSchema::TOTAL_AMOUNT => 100,
        OrderSchema::EXPIRES_AT => now()->addHour(),
    ], $overrides));

    // unique variant per order — inventory stocks are one row per variant
    $variantId = ++$variantSeq;

    $order->items()->create([
        OrderItemSchema::VARIANT_ID => $variantId,
        OrderItemSchema::PRODUCT_NAME_SNAPSHOT => 'Returned Product',
        OrderItemSchema::PRICE_SNAPSHOT => 50,
        OrderItemSchema::QUANTITY => 2,
    ]);

    InventoryStock::query()->create([
        'variant_id' => $variantId,
        'quantity' => 5,
    ]);

    return $order;
}

function requestReturn(Order $order, string $reason = 'damaged') 
{
    $user = User::query()->find($order->{OrderSchema::USER_ID});

    return test()->actingAs($user)->postJson(
        test()->baseUrl('/user/orders/'.$order->{OrderSchema::ORDER_CODE}.'/returns'),
        [OrderReturnSchema::REASON => $reason, OrderReturnSchema::NOTE => 'arrived cracked'],
    );
}

it('creates a return request for an eligible order', function () {
    $user = User::factory()->create();
    $order = shippedPaidOrder($user);

    requestReturn($order)->assertCreated()
        ->assertJsonPath('data.status', OrderReturnStatusEnum::REQUESTED->value)
        ->assertJsonPath('data.reason', 'damaged');

    expect(OrderReturn::query()->count())->toBe(1);
});

it('rejects a second open return for the same order', function () {
    $user = User::factory()->create();
    $order = shippedPaidOrder($user);

    requestReturn($order)->assertCreated();

    requestReturn($order)->assertStatus(400)
        ->assertJsonPath('code', 'order.return.ineligible');
});

it('rejects ineligible orders: unpaid, unshipped, refunded and past the window', function () {
    $user = User::factory()->create();

    requestReturn(shippedPaidOrder($user, [OrderSchema::PAYMENT_STATUS => OrderPaymentStatusEnum::UNPAID, OrderSchema::PAID_AT => null]))
        ->assertStatus(400)->assertJsonPath('code', 'order.return.ineligible');

    requestReturn(shippedPaidOrder($user, [OrderSchema::STATUS => OrderStatusEnum::PROCESSING]))
        ->assertStatus(400)->assertJsonPath('code', 'order.return.ineligible');

    requestReturn(shippedPaidOrder($user, [OrderSchema::PAYMENT_STATUS => OrderPaymentStatusEnum::REFUNDED]))
        ->assertStatus(400)->assertJsonPath('code', 'order.return.ineligible');

    requestReturn(shippedPaidOrder($user, [OrderSchema::PAID_AT => now()->subDays(15)]))
        ->assertStatus(400)->assertJsonPath('code', 'order.return.ineligible');
});

it('runs the customer lifecycle: ship the goods then stays read-only', function () {
    $user = User::factory()->create();
    $order = shippedPaidOrder($user);
    requestReturn($order)->assertCreated();

    $returnId = OrderReturn::query()->first()->{OrderReturnSchema::ID};

    // approve first — only then can the customer ship
    $this->actingAs($this->superAdminUser())
        ->patchJson($this->baseUrl("/admin/returns/{$returnId}"), [
            OrderReturnSchema::STATUS => OrderReturnStatusEnum::APPROVED->value,
        ])->assertOk();

    $this->actingAs($user)
        ->patchJson($this->baseUrl("/user/returns/{$returnId}/shipped"), [
            OrderReturnSchema::TRACKING_NUMBER => 'TRK-1',
        ])->assertOk()
        ->assertJsonPath('data.tracking_number', 'TRK-1');

    // shipped returns are no longer cancellable by the customer
    $this->actingAs($user)
        ->patchJson($this->baseUrl("/user/returns/{$returnId}/cancel"))
        ->assertStatus(400)
        ->assertJsonPath('code', 'order.return.invalid_transition');
});

it('restores stock exactly once when two receives race the idempotency flag', function () {
    $user = User::factory()->create();
    $order = shippedPaidOrder($user);
    requestReturn($order)->assertCreated();

    $return = OrderReturn::query()->first();

    foreach (['approved', 'shipped'] as $status) {
        $this->actingAs($this->superAdminUser())
            ->patchJson($this->baseUrl("/admin/returns/{$return->id}"), [
                OrderReturnSchema::STATUS => $status,
            ])->assertOk();
    }

    $receive = fn () => $this->actingAs($this->superAdminUser())
        ->patchJson($this->baseUrl("/admin/returns/{$return->id}"), [
            OrderReturnSchema::STATUS => OrderReturnStatusEnum::RECEIVED->value,
        ]);

    $receive()->assertOk();

    // a replayed receive (retry / second admin) is idempotent: it
    // succeeds again but the stock_restored_at flag blocks a second restock
    $receive()->assertOk();

    // 5 at creation + 2 restored exactly once, despite the replayed receive
    $variantId = $order->items()->first()->{OrderItemSchema::VARIANT_ID};
    expect((int) InventoryStock::query()->where('variant_id', $variantId)->value('quantity'))->toBe(7)
        ->and($return->refresh()->{OrderReturnSchema::STOCK_RESTORED_AT})->not->toBeNull();
});

it('can receive without restocking when the goods are damaged', function () {
    $user = User::factory()->create();
    $order = shippedPaidOrder($user);
    requestReturn($order)->assertCreated();

    $return = OrderReturn::query()->first();

    foreach (['approved', 'shipped'] as $status) {
        $this->actingAs($this->superAdminUser())
            ->patchJson($this->baseUrl("/admin/returns/{$return->id}"), [OrderReturnSchema::STATUS => $status])
            ->assertOk();
    }

    $this->actingAs($this->superAdminUser())
        ->patchJson($this->baseUrl("/admin/returns/{$return->id}"), [
            OrderReturnSchema::STATUS => OrderReturnStatusEnum::RECEIVED->value,
            OrderReturnSchema::REQ_RESTORE_STOCK => false,
        ])->assertOk();

    $variantId = $order->items()->first()->{OrderItemSchema::VARIANT_ID};
    expect((int) InventoryStock::query()->where('variant_id', $variantId)->value('quantity'))->toBe(5)
        ->and($return->refresh()->{OrderReturnSchema::STOCK_RESTORED_AT})->toBeNull();
});

it('rejects a customer touching someone else\'s return or order', function () {
    $owner = User::factory()->create();
    $order = shippedPaidOrder($owner);
    requestReturn($order)->assertCreated();

    $returnId = OrderReturn::query()->first()->{OrderReturnSchema::ID};

    $intruder = User::factory()->create();
    $this->actingAs($intruder)
        ->patchJson($this->baseUrl("/user/returns/{$returnId}/cancel"))
        ->assertStatus(404);

    $this->actingAs($intruder)
        ->postJson($this->baseUrl('/user/orders/'.$order->{OrderSchema::ORDER_CODE}.'/returns'), [
            OrderReturnSchema::REASON => 'other',
        ])->assertStatus(404);
});

it('denies the admin return endpoints without permission', function () {
    $this->actingAs(User::factory()->create())
        ->getJson($this->baseUrl('/admin/returns'))
        ->assertStatus(403);
});
