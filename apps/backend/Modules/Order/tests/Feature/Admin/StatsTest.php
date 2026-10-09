<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Order\Models\Order;
use Modules\Order\Models\OrderItem;
use Modules\Order\Schemas\Order\OrderPaymentStatusEnum;
use Modules\Order\Schemas\Order\OrderSchema;
use Modules\Order\Schemas\Order\OrderStatusEnum;
use Modules\Order\Schemas\OrderItem\OrderItemSchema;
use Modules\Order\Schemas\Stats\StatsSchema;
use Modules\Order\Tests\Feature\HelperTrait;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

function paidOrder(float $total, ?string $createdAt = null): Order
{
    $order = Order::factory()->create([
        OrderSchema::STATUS => OrderStatusEnum::COMPLETED,
        OrderSchema::PAYMENT_STATUS => OrderPaymentStatusEnum::PAID,
        OrderSchema::TOTAL_AMOUNT => $total,
    ]);

    if ($createdAt !== null) {
        $order->forceFill([OrderSchema::CREATED_AT => $createdAt])->save();
    }

    return $order;
}

it('shows order stats for an admin', function () {
    $order = paidOrder(100.00);
    paidOrder(50.00, now()->subMonth()->startOfMonth()->addDay()->format('Y-m-d H:i:s'));

    Order::factory()->create([
        OrderSchema::STATUS => OrderStatusEnum::PENDING,
        OrderSchema::PAYMENT_STATUS => OrderPaymentStatusEnum::UNPAID,
        OrderSchema::TOTAL_AMOUNT => 30.00,
    ]);

    OrderItem::factory()->create([
        OrderItemSchema::ORDER_ID => $order->{OrderSchema::ID},
        OrderItemSchema::PRODUCT_NAME_SNAPSHOT => 'Test Product',
        OrderItemSchema::QUANTITY => 3,
    ]);

    $this->actingAs($this->superAdminUser())
        ->getJson($this->baseUrl('/admin/stats'))
        ->assertOk()
        ->assertJsonPath('data.'.StatsSchema::RES_TOTAL_REVENUE, '150.00')
        ->assertJsonPath('data.'.StatsSchema::RES_AVG_ORDER_VALUE, '75.00')
        ->assertJsonPath('data.'.StatsSchema::RES_ORDERS_COUNT, 3)
        ->assertJsonPath('data.'.StatsSchema::RES_PAID_ORDERS_COUNT, 2)
        ->assertJsonPath('data.'.StatsSchema::RES_PENDING_ORDERS_COUNT, 1)
        ->assertJsonCount(12, 'data.'.StatsSchema::RES_REVENUE_BY_MONTH)
        ->assertJsonCount(30, 'data.'.StatsSchema::RES_ORDERS_PER_DAY)
        ->assertJsonCount(count(OrderStatusEnum::cases()), 'data.'.StatsSchema::RES_ORDERS_BY_STATUS)
        ->assertJsonPath('data.'.StatsSchema::RES_TOP_PRODUCTS.'.0.'.StatsSchema::RES_NAME, 'Test Product')
        ->assertJsonPath('data.'.StatsSchema::RES_TOP_PRODUCTS.'.0.'.StatsSchema::RES_QUANTITY, 3)
        ->assertJsonCount(3, 'data.'.StatsSchema::RES_RECENT_ORDERS);
});

it('nulls the change percent when the previous month has no revenue', function () {
    paidOrder(120.00);

    $this->actingAs($this->superAdminUser())
        ->getJson($this->baseUrl('/admin/stats'))
        ->assertOk()
        ->assertJsonPath('data.'.StatsSchema::RES_REVENUE_CHANGE_PERCENT, null)
        ->assertJsonPath('data.'.StatsSchema::RES_TOTAL_REVENUE, '120.00');
});

it('requires authentication', function () {
    $this->getJson($this->baseUrl('/admin/stats'))->assertUnauthorized();
});
