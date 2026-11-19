<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Modules\Auth\Models\User;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Models\ProductTranslation;
use Modules\Catalog\Models\Variant;
use Modules\Core\Contracts\Events\Order\OrderCreatedEvent;
use Modules\Core\Contracts\Events\Order\OrderPaidEvent;
use Modules\Core\Contracts\Events\Order\OrderRefundedEvent;
use Modules\Core\Contracts\Gateways\Order\DTOs\CreateOrderInput;
use Modules\Core\Contracts\Gateways\User\DTOs\AddressDto;
use Modules\Core\Models\Currency;
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

/** Minimal input carrying the transaction-time currency identity. */
function createSnapshotInputFor(Variant $variant, int $currencyId): CreateOrderInput
{
    $input = createInputFor($variant);
    $input->currency_id = $currencyId;
    $input->display_currency_code = 'EUR';
    $input->display_fx_rate = '0.92000000';

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

it('switches the totals formula by pricing mode and snapshots it (gap-C1b)', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $product = Product::factory()->create();
    $variant = Variant::factory()->ofProduct($product)->create();

    // Inclusive input: 300 gross items + 5 gross shipping, 47.40
    // extracted tax — the total must NOT add it (the double-charge trap).
    $input = createInputFor($variant);
    $input->prices_contain_tax = true;
    $input->tax_amount_minor = 4740;

    $orderCode = app(OrderGateway::class)->createFromCart($input);

    $order = Order::query()->where(OrderSchema::ORDER_CODE, $orderCode)->first();

    expect((bool) $order->{OrderSchema::PRICES_CONTAIN_TAX})->toBeTrue()
        // total = 300 + 5 (gross already contains the tax)
        ->and((float) $order->{OrderSchema::TOTAL_AMOUNT})->toBe(305.0)
        // the extracted tax is snapshotted as INFORMATIONAL
        ->and((float) $order->{OrderSchema::TAX_AMOUNT})->toBe(47.40);

    // Exclusive on the same input shape: tax adds exactly once.
    $exclusive = createInputFor($variant);
    $exclusive->tax_amount_minor = 4740;
    $exclusiveCode = app(OrderGateway::class)->createFromCart($exclusive);
    $exclusiveOrder = Order::query()->where(OrderSchema::ORDER_CODE, $exclusiveCode)->first();

    expect((bool) $exclusiveOrder->{OrderSchema::PRICES_CONTAIN_TAX})->toBeFalse()
        ->and((float) $exclusiveOrder->{OrderSchema::TOTAL_AMOUNT})->toBe(352.40)
        ->and((float) $exclusiveOrder->{OrderSchema::TAX_AMOUNT})->toBe(47.40);
});

it('freezes the VAT id and region evidence on the order (gap-C1c)', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $product = Product::factory()->create();
    $variant = Variant::factory()->ofProduct($product)->create();

    $input = createInputFor($variant);
    $input->address->region_code = 'US-CA';
    $input->address->vat_id = 'DE123456789';

    $orderCode = app(OrderGateway::class)->createFromCart($input);
    $order = Order::query()->where(OrderSchema::ORDER_CODE, $orderCode)->first();

    expect($order->{OrderSchema::BUYER_VAT_ID})->toBe('DE123456789');

    $address = $order->addresses()->first();

    expect($address->{OrderAddressSchema::REGION_CODE})->toBe('US-CA')
        ->and($address->{OrderAddressSchema::VAT_ID})->toBe('DE123456789');
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

it('carries the transaction-time currency identity on order lifecycle events', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $product = Product::factory()->create();
    $variant = Variant::factory()->ofProduct($product)->create();
    $currency = Currency::factory()->create([
        'code' => 'EUR', 'exchange_rate' => '0.92000000',
    ]);
    $gateway = app(OrderGateway::class);

    // Targeted fakes (not Event::fakeFor): the blanket fake also swallows
    // model events, and Order::creating generates order_code.
    Event::fake([OrderCreatedEvent::class]);
    $orderCode = $gateway->createFromCart(createSnapshotInputFor($variant, $currency->id));
    expect($orderCode)->not->toBeNull();

    // createFromCart: the toArray() payload already carries the snapshot;
    // the event contract must surface it (FillTrait drops undeclared keys).
    Event::assertDispatched(OrderCreatedEvent::class, fn (OrderCreatedEvent $e) => $e->currency_id === $currency->id
        && $e->display_currency_code === 'EUR'
        && (float) $e->display_fx_rate === 0.92);

    $order = Order::query()->where(OrderSchema::ORDER_CODE, $orderCode)->first();

    // markAsPaid: the explicit payload must carry the same identity.
    Event::fake([OrderPaidEvent::class]);
    expect($gateway->markAsPaid($order->id))->toBeTrue();
    Event::assertDispatched(OrderPaidEvent::class, fn (OrderPaidEvent $e) => $e->currency_id === $currency->id
        && $e->display_currency_code === 'EUR'
        && (float) $e->display_fx_rate === 0.92);

    // applyRefund (ledger-owned full refund) carries it too.
    Event::fake([OrderRefundedEvent::class]);
    $totalMinor = (int) round((float) $order->{OrderSchema::TOTAL_AMOUNT} * 100);
    expect($gateway->applyRefund($order->id, $totalMinor, $totalMinor))->toBeTrue();
    Event::assertDispatched(OrderRefundedEvent::class, fn (OrderRefundedEvent $e) => $e->currency_id === $currency->id
        && $e->display_currency_code === 'EUR'
        && (float) $e->display_fx_rate === 0.92);
});

it('keeps identical snapshot values through webhook and admin paid transitions', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $product = Product::factory()->create();
    $variant = Variant::factory()->ofProduct($product)->create();
    $currency = Currency::factory()->create([
        'code' => 'EUR', 'exchange_rate' => '0.92000000',
    ]);

    // Same checkout facts reaching paid through two different mechanisms.
    $orderCodes = [
        app(OrderGateway::class)->createFromCart(createSnapshotInputFor($variant, $currency->id)),
        app(OrderGateway::class)->createFromCart(createSnapshotInputFor($variant, $currency->id)),
    ];
    $orders = Order::query()
        ->whereIn(OrderSchema::ORDER_CODE, $orderCodes)
        ->get()
        ->keyBy(OrderSchema::ORDER_CODE);

    Event::fake([OrderPaidEvent::class]);

    // Path A: the webhook funnel (markAsPaid).
    app(OrderGateway::class)->markAsPaid($orders[$orderCodes[0]]->id);

    // Path B: the admin panel payment-status transition.
    $this->actingAs($this->superAdminUser());
    $this->patchJson('api/v1/order/admin/orders/'.$orders[$orderCodes[1]]->id, [
        OrderSchema::PAYMENT_STATUS => OrderPaymentStatusEnum::PAID->value,
    ])->assertOk();

    // Both paths must carry the identical transaction-time identity for the
    // same checkout facts — notification rendering cannot depend on which
    // mechanism processed the transition.
    $captured = [];
    Event::assertDispatched(OrderPaidEvent::class, function (OrderPaidEvent $e) use (&$captured): bool {
        $captured[] = [$e->currency_id, $e->display_currency_code, (float) $e->display_fx_rate];

        return true;
    });
    expect($captured)->toHaveCount(2)
        ->and($captured[0])->toBe([$currency->id, 'EUR', 0.92])
        ->and($captured[1])->toBe($captured[0]);
});

it('hydrates the currency fields as null when a payload omits them (legacy FillTrait payloads)', function () {
    /** @var OrderRefundedEvent $event */
    $event = OrderRefundedEvent::fill([
        'id' => 1,
        'user_id' => 2,
        'order_code' => 'ORD-1',
        'payment_status' => 'refunded',
        'total_amount' => 150.50,
    ]);

    expect($event->currency_id)->toBeNull()
        ->and($event->display_currency_code)->toBeNull()
        ->and($event->display_fx_rate)->toBeNull();
});
