<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Modules\Auth\Models\User;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Models\ProductTranslation;
use Modules\Catalog\Models\Variant;
use Modules\Core\Contracts\Events\Order\OrderPaidEvent;
use Modules\Core\Contracts\Gateways\Order\DTOs\CreateOrderInput;
use Modules\Core\Contracts\Gateways\User\DTOs\AddressDto;
use Modules\Order\Gateways\OrderGateway;
use Modules\Order\Models\Order;
use Modules\Order\Schemas\Order\OrderPaymentStatusEnum;
use Modules\Order\Schemas\Order\OrderSchema;
use Modules\Order\Schemas\Order\OrderStatusEnum;
use Modules\Order\Schemas\OrderAddress\OrderAddressSchema;
use Modules\Order\Schemas\OrderItem\OrderItemSchema;
use Modules\Order\Tests\Feature\HelperTrait;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

function createInputFor(Variant $variant): CreateOrderInput
{
    $input = new CreateOrderInput;
    $input->cart_items = [[
        OrderItemSchema::VARIANT_ID => $variant->id,
        OrderItemSchema::PRICE_SNAPSHOT => 150,
        OrderItemSchema::PRODUCT_NAME_SNAPSHOT => 'Test Product',
        OrderItemSchema::QUANTITY => 2,
    ]];
    $input->address = AddressDto::fill([
        'full_name' => 'Jane Doe',
        'phone' => '+123456789',
        'address_line' => '1 Main St',
        'city' => 'Springfield',
        'postal_code' => '12345',
        'country_code' => 'US',
    ]);
    $input->shipping_method_id = 9;
    $input->shipping_method_name = 'Express';
    $input->shipping_cost = 5.0;

    return $input;
}

it('creates an order from a cart with item snapshots, address, expiry and total', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $product = Product::factory()->create();
    $variant = Variant::factory()->ofProduct($product)->create();
    ProductTranslation::query()->create([
        'product_id' => $product->id,
        'language_id' => 1,
        'title' => 'Test Product',
        'slug' => 'test-product',
        'content' => 'Test content',
    ]);

    $orderCode = app(OrderGateway::class)->createFromCart(createInputFor($variant));

    expect($orderCode)->not->toBeNull();

    $order = Order::query()->where(OrderSchema::ORDER_CODE, $orderCode)->first();
    expect($order)->not->toBeNull()
        ->and((int) $order->{OrderSchema::USER_ID})->toBe($user->id)
        ->and($order->{OrderSchema::STATUS})->toBe(OrderStatusEnum::PENDING)
        ->and($order->{OrderSchema::PAYMENT_STATUS})->toBe(OrderPaymentStatusEnum::UNPAID)
        ->and($order->{OrderSchema::SHIPPING_METHOD_NAME})->toBe('Express')
        ->and((float) $order->{OrderSchema::SHIPPING_COST})->toBe(5.0)
        // items + shipping: 150 × 2 + 5
        ->and((float) $order->{OrderSchema::TOTAL_AMOUNT})->toBe(305.0)
        // reserved for 30 minutes of pending payment
        ->and($order->{OrderSchema::EXPIRES_AT}->gt(now()->addMinutes(29)))->toBeTrue()
        ->and($order->{OrderSchema::EXPIRES_AT}->lt(now()->addMinutes(31)))->toBeTrue();

    $item = $order->items()->first();
    expect((int) $item->{OrderItemSchema::VARIANT_ID})->toBe($variant->id)
        ->and((int) $item->{OrderItemSchema::PRICE_SNAPSHOT})->toBe(150)
        ->and($item->{OrderItemSchema::PRODUCT_NAME_SNAPSHOT})->toBe('Test Product')
        ->and($item->{OrderItemSchema::PRODUCT_SLUG_SNAPSHOT})->toBe('test-product')
        ->and((int) $item->{OrderItemSchema::QUANTITY})->toBe(2);

    expect($order->addresses()->get())->toHaveCount(1)
        ->and($order->addresses()->first()->{OrderAddressSchema::FULL_NAME})->toBe('Jane Doe');
});

it('marks an order as paid exactly once and starts fulfilment', function () {
    $user = User::factory()->create();
    $order = Order::factory()->create([
        OrderSchema::USER_ID => $user->id,
        OrderSchema::STATUS => OrderStatusEnum::PENDING,
        OrderSchema::PAYMENT_STATUS => OrderPaymentStatusEnum::UNPAID,
    ]);

    Event::fakeFor(function () use ($order): void {
        $gateway = app(OrderGateway::class);

        expect($gateway->markAsPaid($order->id))->toBeTrue();

        $order->refresh();
        expect($order->{OrderSchema::PAYMENT_STATUS})->toBe(OrderPaymentStatusEnum::PAID)
            ->and($order->{OrderSchema::STATUS})->toBe(OrderStatusEnum::PROCESSING)
            ->and($order->{OrderSchema::PAID_AT})->not->toBeNull();

        Event::assertDispatched(OrderPaidEvent::class, 1);

        // A webhook retry acks as a no-op: no second event, paid_at untouched.
        $paidAt = $order->{OrderSchema::PAID_AT};
        expect($gateway->markAsPaid($order->id))->toBeTrue();
        expect($order->refresh()->{OrderSchema::PAID_AT}->equalTo($paidAt))->toBeTrue();

        Event::assertDispatched(OrderPaidEvent::class, 1);
    });
});

it('returns false when marking an unknown order as paid', function () {
    expect(app(OrderGateway::class)->markAsPaid(PHP_INT_MAX))->toBeFalse();
});

it('advances fulfilment through shipped to completed', function () {
    $order = Order::factory()->create([
        OrderSchema::STATUS => OrderStatusEnum::PENDING,
        OrderSchema::PAYMENT_STATUS => OrderPaymentStatusEnum::PAID,
    ]);
    $gateway = app(OrderGateway::class);

    expect($gateway->markAsShipped($order->id))->toBeTrue()
        ->and($order->refresh()->{OrderSchema::STATUS})->toBe(OrderStatusEnum::SHIPPED)
        ->and($gateway->markAsDelivered($order->id))->toBeTrue()
        ->and($order->refresh()->{OrderSchema::STATUS})->toBe(OrderStatusEnum::COMPLETED);
});

it('rejects fulfilment transitions the status machine forbids', function () {
    $cancelled = Order::factory()->create([
        OrderSchema::STATUS => OrderStatusEnum::CANCELLED,
        OrderSchema::PAYMENT_STATUS => OrderPaymentStatusEnum::UNPAID,
    ]);

    $gateway = app(OrderGateway::class);

    expect($gateway->markAsShipped($cancelled->id))->toBeFalse()
        ->and($gateway->markAsDelivered($cancelled->id))->toBeFalse()
        ->and($cancelled->refresh()->{OrderSchema::STATUS})->toBe(OrderStatusEnum::CANCELLED);

    // unpaid orders cannot be refunded directly — pay first
    $unpaid = Order::factory()->create([
        OrderSchema::STATUS => OrderStatusEnum::PROCESSING,
        OrderSchema::PAYMENT_STATUS => OrderPaymentStatusEnum::UNPAID,
    ]);
    expect($gateway->markAsRefunded($unpaid->id))->toBeFalse()
        ->and($unpaid->refresh()->{OrderSchema::PAYMENT_STATUS})->toBe(OrderPaymentStatusEnum::UNPAID);
});
