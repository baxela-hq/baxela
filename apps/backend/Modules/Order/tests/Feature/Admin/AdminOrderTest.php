<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\Models\User;
use Modules\Order\Models\Order;
use Modules\Order\Schemas\Order\OrderPaymentStatusEnum;
use Modules\Order\Schemas\Order\OrderSchema;
use Modules\Order\Schemas\Order\OrderStatusEnum;
use Modules\Order\Tests\Feature\HelperTrait;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

function adminOrder(): Order
{
    return Order::factory()->create([
        OrderSchema::USER_ID => User::factory()->create()->id,
        OrderSchema::STATUS => OrderStatusEnum::PENDING,
        OrderSchema::PAYMENT_STATUS => OrderPaymentStatusEnum::UNPAID,
        OrderSchema::EXPIRES_AT => now()->addHour(),
    ]);
}

it('updates order status and payment status', function () {
    $order = adminOrder();

    $this->actingAs($this->superAdminUser())
        ->patchJson($this->baseUrl('/admin/orders/'.$order->{OrderSchema::ID}), [
            OrderSchema::STATUS => OrderStatusEnum::PROCESSING->value,
            OrderSchema::PAYMENT_STATUS => OrderPaymentStatusEnum::PAID->value,
        ])
        ->assertOk()
        ->assertJsonPath('data.'.OrderSchema::STATUS, OrderStatusEnum::PROCESSING->value)
        ->assertJsonPath('data.'.OrderSchema::PAYMENT_STATUS, OrderPaymentStatusEnum::PAID->value);

    expect($order->refresh())
        ->{OrderSchema::STATUS}->toBe(OrderStatusEnum::PROCESSING)
        ->{OrderSchema::PAYMENT_STATUS}->toBe(OrderPaymentStatusEnum::PAID)
        ->{OrderSchema::PAID_AT}->not->toBeNull();
});

it('rejects an invalid status transition', function () {
    $order = adminOrder();

    $this->actingAs($this->superAdminUser())
        ->patchJson($this->baseUrl('/admin/orders/'.$order->{OrderSchema::ID}), [
            OrderSchema::STATUS => OrderStatusEnum::COMPLETED->value,
            OrderSchema::PAYMENT_STATUS => OrderPaymentStatusEnum::UNPAID->value,
        ])
        ->assertStatus(400)
        ->assertJsonPath('code', 'order.update.invalid_status_transition');

    expect($order->refresh()->{OrderSchema::STATUS})->toBe(OrderStatusEnum::PENDING);
});
