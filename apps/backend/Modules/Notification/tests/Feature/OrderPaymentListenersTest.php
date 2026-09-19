<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Modules\Auth\Models\User;
use Modules\Core\Contracts\Events\Order\OrderCancelledEvent;
use Modules\Core\Contracts\Events\Order\OrderCompletedEvent;
use Modules\Core\Contracts\Events\Order\OrderCreatedEvent;
use Modules\Core\Contracts\Events\Order\OrderPaidEvent;
use Modules\Core\Contracts\Events\Order\OrderShippedEvent;
use Modules\Core\Contracts\Events\Payment\PaymentFailedEvent;
use Modules\Core\Contracts\Events\Payment\PaymentSucceededEvent;
use Modules\Notification\Emails\DynamicNotification;
use Modules\Notification\Models\Notification;
use Modules\Notification\Schemas\Notification\NotificationCodeEnum;
use Modules\Notification\Tests\Feature\HelperTrait;
use Modules\Order\Models\Order;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

function orderCreatedPayload(int $userId, ?string $locale = null): array
{
    return [
        'id' => 1,
        'user_id' => $userId,
        'order_code' => 'ORD-1234',
        'status' => 'pending',
        'total_amount' => 150.5,
        'expires_at' => now()->addMinutes(30)->toDateTimeString(),
        'created_at' => now()->toDateTimeString(),
        'locale' => $locale,
    ];
}

it('notifies staff and the customer when an order is created', function () {
    Mail::fake();

    $admin = $this->superAdminUser();
    $customer = User::factory()->create();

    event(OrderCreatedEvent::fill(orderCreatedPayload($customer->id)));

    expect(
        Notification::query()
            ->where('user_id', $admin->id)
            ->where('code', NotificationCodeEnum::ORDER_ORDER_CREATED->value)
            ->where('audience', 'admin')
            ->where('meta->order_code', 'ORD-1234')
            ->count()
    )->toBe(1)->and(
        Notification::query()
            ->where('user_id', $customer->id)
            ->where('code', NotificationCodeEnum::ORDER_ORDER_CREATED->value)
            ->where('audience', 'user')
            ->count()
    )->toBe(1);

    Mail::assertSent(DynamicNotification::class, 2);
});

it('stores the customer notification in the checkout locale', function () {
    Mail::fake();

    $this->superAdminUser();
    $customer = User::factory()->create();

    event(OrderCreatedEvent::fill(orderCreatedPayload($customer->id, 'fa')));

    $row = Notification::query()
        ->where('user_id', $customer->id)
        ->where('code', NotificationCodeEnum::ORDER_ORDER_CREATED->value)
        ->first();

    expect($row->title)->toBe('سفارش ORD-1234 ثبت شد');
});

it('notifies the customer when an order is paid', function () {
    Mail::fake();

    $this->superAdminUser();
    $customer = User::factory()->create();

    event(OrderPaidEvent::fill([
        'id' => 1,
        'user_id' => $customer->id,
        'order_code' => 'ORD-1234',
        'status' => 'processing',
        'payment_status' => 'paid',
        'total_amount' => 150.5,
    ]));

    expect(
        Notification::query()
            ->where('user_id', $customer->id)
            ->where('code', NotificationCodeEnum::ORDER_ORDER_PAID->value)
            ->count()
    )->toBe(1);

    Mail::assertSent(DynamicNotification::class, 1);
});

it('notifies the customer when an order is shipped', function () {
    Mail::fake();

    $customer = User::factory()->create();

    event(OrderShippedEvent::fill([
        'id' => 1,
        'user_id' => $customer->id,
        'order_code' => 'ORD-1234',
        'status' => 'shipped',
    ]));

    expect(
        Notification::query()
            ->where('user_id', $customer->id)
            ->where('code', NotificationCodeEnum::ORDER_ORDER_SHIPPED->value)
            ->count()
    )->toBe(1);

    Mail::assertSent(DynamicNotification::class, 1);
});

it('records a database-only notification when an order completes', function () {
    Mail::fake();

    $customer = User::factory()->create();

    event(OrderCompletedEvent::fill([
        'id' => 1,
        'user_id' => $customer->id,
        'order_code' => 'ORD-1234',
        'status' => 'completed',
    ]));

    expect(
        Notification::query()
            ->where('user_id', $customer->id)
            ->where('code', NotificationCodeEnum::ORDER_ORDER_COMPLETED->value)
            ->count()
    )->toBe(1);

    Mail::assertNothingSent();
});

it('carries the cancellation reason in meta when an order is cancelled', function () {
    Mail::fake();

    $customer = User::factory()->create();

    event(OrderCancelledEvent::fill([
        'id' => 1,
        'user_id' => $customer->id,
        'order_code' => 'ORD-1234',
        'status' => 'cancelled',
        'reason' => 'expired',
    ]));

    $row = Notification::query()
        ->where('user_id', $customer->id)
        ->where('code', NotificationCodeEnum::ORDER_ORDER_CANCELLED->value)
        ->first();

    expect($row)->not->toBeNull()
        ->and($row->meta['reason'])->toBe('expired');
});

it('notifies staff when a payment succeeds', function () {
    Mail::fake();

    $admin = $this->superAdminUser();
    $customer = User::factory()->create();
    $order = Order::factory()->create([
        'user_id' => $customer->id,
        'order_code' => 'ORD-1234',
    ]);

    event(PaymentSucceededEvent::fill([
        'id' => 1,
        'order_id' => $order->id,
        'amount' => 150.5,
        'status' => 'success',
    ]));

    expect(
        Notification::query()
            ->where('user_id', $admin->id)
            ->where('code', NotificationCodeEnum::PAYMENT_PAYMENT_SUCCEEDED->value)
            ->where('meta->order_code', 'ORD-1234')
            ->count()
    )->toBe(1)->and(
        Notification::query()
            ->where('user_id', $customer->id)
            ->count()
    )->toBe(0);

    Mail::assertNothingSent();
});

it('notifies the customer and staff when a payment fails', function () {
    Mail::fake();

    $admin = $this->superAdminUser();
    $customer = User::factory()->create();
    $order = Order::factory()->create([
        'user_id' => $customer->id,
        'order_code' => 'ORD-1234',
    ]);

    event(PaymentFailedEvent::fill([
        'id' => 1,
        'order_id' => $order->id,
        'amount' => 150.5,
        'status' => 'failed',
    ]));

    expect(
        Notification::query()
            ->where('code', NotificationCodeEnum::PAYMENT_PAYMENT_FAILED->value)
            ->count()
    )->toBe(2)->and(
        Notification::query()
            ->where('user_id', $customer->id)
            ->where('code', NotificationCodeEnum::PAYMENT_PAYMENT_FAILED->value)
            ->exists()
    )->toBeTrue()->and(
        Notification::query()
            ->where('user_id', $admin->id)
            ->where('code', NotificationCodeEnum::PAYMENT_PAYMENT_FAILED->value)
            ->exists()
    )->toBeTrue();
});
