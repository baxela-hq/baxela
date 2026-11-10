<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Modules\Auth\Models\User;
use Modules\Core\Contracts\Events\Order\OrderPaidEvent;
use Modules\Core\Contracts\Gateways\Order\OrderGatewayInterface;
use Modules\Core\Models\Currency;
use Modules\Notification\Emails\DynamicNotification;
use Modules\Order\Models\Order;
use Modules\Order\Models\OrderItem;
use Modules\Order\Schemas\Order\OrderPaymentStatusEnum;
use Modules\Order\Schemas\Order\OrderSchema;
use Modules\Order\Schemas\Order\OrderStatusEnum;
use Modules\Order\Schemas\OrderItem\OrderItemSchema;
use Modules\Order\Tests\Feature\HelperTrait;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

function autoInvoiceOrder(User $user): Order
{
    $currency = Currency::query()->firstOrCreate(
        ['code' => 'EUR'],
        ['name' => 'Euro', 'is_default' => true],
    );

    $order = Order::query()->create([
        OrderSchema::USER_ID => $user->id,
        OrderSchema::ORDER_CODE => 'AUT'.uniqid(),
        OrderSchema::STATUS => OrderStatusEnum::PENDING,
        OrderSchema::PAYMENT_STATUS => OrderPaymentStatusEnum::UNPAID,
        OrderSchema::CURRENCY_ID => $currency->id,
        OrderSchema::TOTAL_AMOUNT => 110.0,
        OrderSchema::TAX_AMOUNT => 10.0,
        OrderSchema::SHIPPING_COST => 0.0,
        OrderSchema::SHIPPING_TAX_AMOUNT => 0.0,
        OrderSchema::DISCOUNT_AMOUNT => 0.0,
        OrderSchema::EXPIRES_AT => now()->addHour(),
    ]);

    OrderItem::query()->create([
        OrderItemSchema::ORDER_ID => $order->id,
        OrderItemSchema::VARIANT_ID => 7,
        OrderItemSchema::PRODUCT_NAME_SNAPSHOT => 'Auto Product',
        OrderItemSchema::PRICE_SNAPSHOT => 55.0,
        OrderItemSchema::QUANTITY => 2,
        OrderItemSchema::TAX_AMOUNT => 10.0,
        OrderItemSchema::TAX_RATE => 10.0,
    ]);

    return $order;
}

it('auto-issues a ready invoice when the order is paid', function () {
    Storage::fake('local');
    Mail::fake();
    $user = User::factory()->create();
    $order = autoInvoiceOrder($user);

    $marked = app(OrderGatewayInterface::class)->markAsPaid($order->id);

    expect($marked)->toBeTrue();

    $invoice = $order->invoices()->first();

    expect($invoice)->not->toBeNull()
        ->and($invoice->pdf_status->value)->toBe('ready');

    // The invoice-ready email went out with the STORED pdf attached.
    Mail::assertSent(DynamicNotification::class, fn ($mail) => $mail->hasTo($user->email)
        && count($mail->attachedDocuments) === 1
        && $mail->attachedDocuments[0]['name'] === $invoice->number.'.pdf');
});

it('does not auto-issue when the setting is off, but manual issue still works', function () {
    Storage::fake('local');
    Mail::fake();
    config(['order.invoices.auto_issue' => false]);
    $user = User::factory()->create();
    $order = autoInvoiceOrder($user);

    app(OrderGatewayInterface::class)->markAsPaid($order->id);

    expect($order->invoices()->count())->toBe(0);

    // The admin surface is unaffected by the gate (D9).
    $this->actingAs($this->superAdminUser())
        ->postJson($this->baseUrl("/admin/orders/{$order->id}/invoice"))
        ->assertCreated();
});

it('auto-issue is idempotent under event replays', function () {
    Storage::fake('local');
    Mail::fake();
    $user = User::factory()->create();
    $order = autoInvoiceOrder($user);

    app(OrderGatewayInterface::class)->markAsPaid($order->id);

    // A duplicate paid event (replay/retry) must not renumber.
    event(OrderPaidEvent::fill([
        'id' => $order->id,
        'user_id' => $user->id,
        'order_code' => $order->order_code,
        'status' => 'processing',
        'payment_status' => 'paid',
        'total_amount' => 110.0,
        'locale' => 'en',
    ]));

    expect($order->invoices()->count())->toBe(1);
});

it('lists a customer their own invoices for an order', function () {
    Storage::fake('local');
    Mail::fake();
    $user = User::factory()->create();
    $order = autoInvoiceOrder($user);
    app(OrderGatewayInterface::class)->markAsPaid($order->id);

    $response = $this->actingAs($user)
        ->getJson($this->baseUrl("/user/orders/{$order->order_code}/invoices"))
        ->assertOk();

    expect($response->json('data.0.number'))->toBe($order->invoices()->first()->number)
        ->and($response->json('data.0.pdf_ready'))->toBeTrue();
});

it('hides another customer\'s order invoices behind a 404', function () {
    Storage::fake('local');
    Mail::fake();
    $owner = User::factory()->create();
    $order = autoInvoiceOrder($owner);
    app(OrderGatewayInterface::class)->markAsPaid($order->id);

    $intruder = User::factory()->create();

    $this->actingAs($intruder)
        ->getJson($this->baseUrl("/user/orders/{$order->order_code}/invoices"))
        ->assertStatus(404);
});

it('lets the owner download their invoice pdf, and nobody else', function () {
    Storage::fake('local');
    Mail::fake();
    $owner = User::factory()->create();
    $order = autoInvoiceOrder($owner);
    app(OrderGatewayInterface::class)->markAsPaid($order->id);
    $invoice = $order->invoices()->first();

    // A guest hits auth first (before any actingAs in this test).
    $this->getJson($this->baseUrl("/user/invoices/{$invoice->id}/pdf"))->assertUnauthorized();

    $response = $this->actingAs($owner)
        ->getJson($this->baseUrl("/user/invoices/{$invoice->id}/pdf"));
    $bytes = (string) Storage::disk('local')->get($invoice->pdf_path);

    $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
    expect($response->streamedContent())->toBe($bytes);

    // The plan's review §7 case: another customer's invoice must not
    // download — a 404, not a 403 (ids stay non-enumerable).
    $intruder = User::factory()->create();

    $this->actingAs($intruder)
        ->getJson($this->baseUrl("/user/invoices/{$invoice->id}/pdf"))
        ->assertStatus(404);
});

it('refuses a not-ready invoice on the user surface too', function () {
    Storage::fake('local');
    Mail::fake();
    $owner = User::factory()->create();
    $order = autoInvoiceOrder($owner);
    app(OrderGatewayInterface::class)->markAsPaid($order->id);
    $invoice = $order->invoices()->first();

    $invoice->forceFill(['pdf_status' => 'generation_failed'])->save();

    $this->actingAs($owner)
        ->getJson($this->baseUrl("/user/invoices/{$invoice->id}/pdf"))
        ->assertStatus(409)
        ->assertJsonPath('code', 'order.invoice.pdf_not_ready');
});
