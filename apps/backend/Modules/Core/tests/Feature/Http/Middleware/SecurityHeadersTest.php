<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Modules\Auth\Models\User;
use Modules\Core\Contracts\Gateways\Order\OrderGatewayInterface;
use Modules\Core\Http\Middleware\SetSecurityHeaders;
use Modules\Core\Models\Currency;
use Modules\Order\Models\Order;
use Modules\Order\Models\OrderItem;
use Modules\Order\Schemas\Order\OrderPaymentStatusEnum;
use Modules\Order\Schemas\Order\OrderSchema;
use Modules\Order\Schemas\Order\OrderStatusEnum;
use Modules\Order\Schemas\OrderItem\OrderItemSchema;
use Modules\Order\Tests\Feature\HelperTrait as OrderHelperTrait;

uses(RefreshDatabase::class);
uses(OrderHelperTrait::class);

function s3InvoiceOrder(User $user): Order
{
    $currency = Currency::query()->firstOrCreate(
        ['code' => 'EUR'],
        ['name' => 'Euro', 'is_default' => true],
    );

    $order = Order::query()->create([
        OrderSchema::USER_ID => $user->id,
        OrderSchema::ORDER_CODE => 'S3'.uniqid(),
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
        OrderItemSchema::PRODUCT_NAME_SNAPSHOT => 'S3 Product',
        OrderItemSchema::PRICE_SNAPSHOT => 55.0,
        OrderItemSchema::QUANTITY => 2,
        OrderItemSchema::TAX_AMOUNT => 10.0,
        OrderItemSchema::TAX_RATE => 10.0,
    ]);

    return $order;
}

it('sends CSP on success and no HSTS over plain HTTP', function () {
    $user = User::factory()->active()->create(['password' => 'Passw0rd!']);

    $response = $this->postJson('api/v1/auth/public/auth/signin', [
        'email' => $user->email,
        'password' => 'Passw0rd!',
    ])->assertOk();

    expect($response->headers->get(SetSecurityHeaders::CSP_HEADER))
        ->toBe(SetSecurityHeaders::CSP_VALUE)
        ->and($response->headers->get(SetSecurityHeaders::HSTS_HEADER))->toBeNull();
});

it('sends HSTS when the request itself is secure', function () {
    $user = User::factory()->active()->create(['password' => 'Passw0rd!']);

    // An absolute https URL passes through url() unchanged, and Symfony's
    // Request::create then sets HTTPS=on from the scheme — a genuine
    // $request->secure() true (withServerVariables cannot do this:
    // Request::create unsets HTTPS for http:// test URLs).
    $response = $this->postJson(
        'https://localhost/api/v1/auth/public/auth/signin',
        ['email' => $user->email, 'password' => 'Passw0rd!'],
    )->assertOk();

    expect($response->headers->get(SetSecurityHeaders::HSTS_HEADER))
        ->toBe(SetSecurityHeaders::HSTS_VALUE);
});

it('ignores X-Forwarded-Proto when nothing is trusted (spoof)', function () {
    $user = User::factory()->active()->create(['password' => 'Passw0rd!']);

    $response = $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.20'])->postJson(
        'api/v1/auth/public/auth/signin',
        ['email' => $user->email, 'password' => 'Passw0rd!'],
        ['X-Forwarded-Proto' => 'https'],
    )->assertOk();

    expect($response->headers->get(SetSecurityHeaders::HSTS_HEADER))->toBeNull();
});

it('ignores X-Forwarded-Proto from a network peer that is not trusted', function () {
    config(['core.trusted_proxies' => '10.0.0.10']);
    $user = User::factory()->active()->create(['password' => 'Passw0rd!']);

    $response = $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.20'])->postJson(
        'api/v1/auth/public/auth/signin',
        ['email' => $user->email, 'password' => 'Passw0rd!'],
        ['X-Forwarded-Proto' => 'https'],
    )->assertOk();

    expect($response->headers->get(SetSecurityHeaders::HSTS_HEADER))->toBeNull();
});

it('honors X-Forwarded-Proto from a trusted proxy address', function () {
    config(['core.trusted_proxies' => '10.0.0.10']);
    $user = User::factory()->active()->create(['password' => 'Passw0rd!']);

    $response = $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.10'])->postJson(
        'api/v1/auth/public/auth/signin',
        ['email' => $user->email, 'password' => 'Passw0rd!'],
        ['X-Forwarded-Proto' => 'https'],
    )->assertOk();

    expect($response->headers->get(SetSecurityHeaders::HSTS_HEADER))
        ->toBe(SetSecurityHeaders::HSTS_VALUE);
});

it('sends CSP on validation errors from a matched route', function () {
    $user = User::factory()->active()->create(['password' => 'Passw0rd!']);

    $response = $this->postJson('api/v1/auth/public/auth/signin', [
        'email' => $user->email,
    ])->assertStatus(422);

    expect($response->headers->get(SetSecurityHeaders::CSP_HEADER))
        ->toBe(SetSecurityHeaders::CSP_VALUE);
});

it('sends CSP on unmatched api routes through the exception renderer', function () {
    $response = $this->getJson('api/v1/definitely/not/a/route')->assertStatus(404);

    expect($response->headers->get(SetSecurityHeaders::CSP_HEADER))
        ->toBe(SetSecurityHeaders::CSP_VALUE);
});

it('applies the headers exactly once when both hooks process a response', function () {
    $user = User::factory()->active()->create(['password' => 'Passw0rd!']);

    // A matched-route 422: the exception is rendered inside the routing
    // pipeline (middleware applies it) AND the render closure runs.
    $response = $this->postJson('api/v1/auth/public/auth/signin', [
        'email' => $user->email,
    ])->assertStatus(422);

    expect($response->headers->all(SetSecurityHeaders::CSP_HEADER))
        ->toHaveCount(1);
});

it('keeps Retry-After and adds CSP on a throttled sign-in', function () {
    config(['auth.rate_limit.sign_in' => 2]);
    $user = User::factory()->active()->create(['password' => 'Passw0rd!']);
    $payload = ['email' => $user->email, 'password' => 'Passw0rd!'];

    $this->postJson('api/v1/auth/public/auth/signin', $payload)->assertOk();
    $this->postJson('api/v1/auth/public/auth/signin', $payload)->assertOk();
    $third = $this->postJson('api/v1/auth/public/auth/signin', $payload);

    $third->assertStatus(429);

    expect($third->headers->get('Retry-After'))->not->toBeNull()
        ->and($third->headers->get(SetSecurityHeaders::CSP_HEADER))
        ->toBe(SetSecurityHeaders::CSP_VALUE);
});

it('does not send the API CSP on non-api json routes', function () {
    $response = $this->getJson('/')->assertOk();

    expect($response->headers->get(SetSecurityHeaders::CSP_HEADER))->toBeNull();
});

it('keeps the pdf download response intact alongside CSP', function () {
    Storage::fake('local');
    Mail::fake();
    $owner = User::factory()->create();
    $order = s3InvoiceOrder($owner);
    app(OrderGatewayInterface::class)->markAsPaid($order->id);
    $invoice = $order->invoices()->first();

    $response = $this->actingAs($owner)
        ->getJson($this->baseUrl("/user/invoices/{$invoice->id}/pdf"));

    $response->assertOk()->assertHeader('Content-Type', 'application/pdf');

    expect($response->headers->get('Content-Disposition'))->toContain('attachment')
        ->and($response->headers->get(SetSecurityHeaders::CSP_HEADER))
        ->toBe(SetSecurityHeaders::CSP_VALUE);
});
