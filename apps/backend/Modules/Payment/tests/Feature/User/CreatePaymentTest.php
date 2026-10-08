<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\Models\User;
use Modules\Core\Models\Currency;
use Modules\Core\Schemas\Currency\CurrencySchema;
use Modules\Order\Models\Order;
use Modules\Order\Schemas\Order\OrderPaymentStatusEnum;
use Modules\Order\Schemas\Order\OrderSchema;
use Modules\Order\Schemas\Order\OrderStatusEnum;
use Modules\Payment\Gateways\AdyenCheckout;
use Modules\Payment\Gateways\MercadopagoClient;
use Modules\Payment\Gateways\NowpaymentsClient;
use Modules\Payment\Gateways\PaypalClient;
use Modules\Payment\Gateways\StripeCheckout;
use Modules\Payment\Models\Payment;
use Modules\Payment\Models\PaymentMethod;
use Modules\Payment\Schemas\Payment\PaymentMethodEnum;
use Modules\Payment\Schemas\Payment\PaymentMethodSchema;
use Modules\Payment\Schemas\Payment\PaymentSchema;
use Modules\Payment\Schemas\Payment\PaymentStatusEnum;
use Modules\Payment\Tests\Feature\HelperTrait;
use Stripe\Checkout\Session as StripeSession;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

function payableOrder(User $user): Order
{
    return Order::factory()->create([
        OrderSchema::USER_ID => $user->id,
        OrderSchema::STATUS => OrderStatusEnum::PENDING,
        OrderSchema::PAYMENT_STATUS => OrderPaymentStatusEnum::UNPAID,
        OrderSchema::EXPIRES_AT => now()->addMinutes(20),
        OrderSchema::TOTAL_AMOUNT => 305,
        OrderSchema::CURRENCY_ID => 2,
    ]);
}

function activeMethod(PaymentMethodEnum $method): PaymentMethod
{
    return PaymentMethod::query()->create([
        PaymentMethodSchema::METHOD => $method,
        PaymentMethodSchema::IS_ACTIVE => true,
        PaymentMethodSchema::SORT_ORDER => 10,
    ]);
}

it('creates a pending manual payment for a payable order', function () {
    activeMethod(PaymentMethodEnum::MANUAL);
    $user = User::factory()->create();
    $this->actingAs($user);
    $order = payableOrder($user);

    $response = $this->postJson($this->baseUrl('/user/process'), [
        'order_code' => $order->{OrderSchema::ORDER_CODE},
        'method' => 'manual',
    ])->assertOk();

    $payment = Payment::query()->find($response->json('data.payment_id'));

    // The manual driver has no hosted checkout: no redirect, settled by an admin
    expect($response->json('data.payment_url'))->toBeNull()
        ->and($payment)->not->toBeNull()
        ->and((int) $payment->{PaymentSchema::ORDER_ID})->toBe($order->id)
        ->and($payment->{PaymentSchema::STATUS})->toBe(PaymentStatusEnum::PENDING)
        ->and($payment->{PaymentSchema::METHOD})->toBe(PaymentMethodEnum::MANUAL)
        ->and((float) $payment->{PaymentSchema::AMOUNT})->toBe(305.0)
        ->and($payment->{PaymentSchema::CURRENCY_ID})->toBe(2);
});

it('rejects paying an already-settled order', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $order = payableOrder($user);
    $order->update([OrderSchema::PAYMENT_STATUS => OrderPaymentStatusEnum::PAID]);

    $this->postJson($this->baseUrl('/user/process'), [
        'order_code' => $order->{OrderSchema::ORDER_CODE},
        'method' => 'manual',
    ])->assertStatus(400)->assertJsonPath('code', 'payment.process.invalid_order');

    expect(Payment::count())->toBe(0);
});

it('rejects paying an expired order', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $order = payableOrder($user);
    $order->update([OrderSchema::EXPIRES_AT => now()->subMinute()]);

    $this->postJson($this->baseUrl('/user/process'), [
        'order_code' => $order->{OrderSchema::ORDER_CODE},
        'method' => 'manual',
    ])->assertStatus(400)->assertJsonPath('code', 'payment.process.invalid_order');
});

it('rejects paying another user\'s order', function () {
    $this->actingAs(User::factory()->create());
    $order = payableOrder(User::factory()->create());

    $this->postJson($this->baseUrl('/user/process'), [
        'order_code' => $order->{OrderSchema::ORDER_CODE},
        'method' => 'manual',
    ])->assertStatus(400)->assertJsonPath('code', 'payment.process.invalid_order');
});

it('rejects a method an admin deactivated', function () {
    PaymentMethod::query()->create([
        PaymentMethodSchema::METHOD => PaymentMethodEnum::MANUAL,
        PaymentMethodSchema::IS_ACTIVE => false,
        PaymentMethodSchema::SORT_ORDER => 10,
    ]);
    $user = User::factory()->create();
    $this->actingAs($user);
    $order = payableOrder($user);

    $this->postJson($this->baseUrl('/user/process'), [
        'order_code' => $order->{OrderSchema::ORDER_CODE},
        'method' => 'manual',
    ])->assertStatus(400)->assertJsonPath('code', 'payment.process.method_inactive');

    expect(Payment::count())->toBe(0);
});

it('rejects a method with no configured driver', function () {
    // paypal is registered by default; drop it from the registry to cover
    // the enum-case-without-driver gap.
    config(['payment.drivers' => collect(config('payment.drivers'))->except('paypal')->all()]);
    $user = User::factory()->create();
    $this->actingAs($user);
    $order = payableOrder($user);

    $this->postJson($this->baseUrl('/user/process'), [
        'order_code' => $order->{OrderSchema::ORDER_CODE},
        'method' => 'paypal',
    ])->assertStatus(400)->assertJsonPath('code', 'payment.process.method_not_supported');
});

it('creates a stripe payment and returns the hosted checkout url', function () {
    activeMethod(PaymentMethodEnum::STRIPE);
    $user = User::factory()->create();
    $this->actingAs($user);
    Currency::factory()->create([
        'id' => 2,
        CurrencySchema::CODE => 'USD',
        CurrencySchema::DECIMAL_PLACES => 2,
    ]);
    $order = payableOrder($user);
    // The session id doubles as the stored transaction_id the webhook matches on.
    $checkout = Mockery::mock(StripeCheckout::class);
    $checkout->shouldReceive('createSession')->once()->andReturn(StripeSession::constructFrom([
        'id' => 'cs_test_9',
        'url' => 'https://checkout.stripe.com/pay/cs_test_9',
    ]));
    $this->app->instance(StripeCheckout::class, $checkout);

    $response = $this->postJson($this->baseUrl('/user/process'), [
        'order_code' => $order->{OrderSchema::ORDER_CODE},
        'method' => 'stripe',
    ])->assertOk();

    $payment = Payment::query()->find($response->json('data.payment_id'));

    expect($response->json('data.payment_url'))->toBe('https://checkout.stripe.com/pay/cs_test_9')
        ->and($payment)->not->toBeNull()
        ->and($payment->{PaymentSchema::TRANSACTION_ID})->toBe('cs_test_9')
        ->and($payment->{PaymentSchema::STATUS})->toBe(PaymentStatusEnum::PENDING)
        ->and($payment->{PaymentSchema::METHOD})->toBe(PaymentMethodEnum::STRIPE);
});

it('rejects a stripe payment when the gateway is not configured', function () {
    config(['payment.stripe.secret' => null]);
    activeMethod(PaymentMethodEnum::STRIPE);
    $user = User::factory()->create();
    $this->actingAs($user);
    $order = payableOrder($user);

    $this->postJson($this->baseUrl('/user/process'), [
        'order_code' => $order->{OrderSchema::ORDER_CODE},
        'method' => 'stripe',
    ])->assertStatus(400)->assertJsonPath('code', 'payment.process.gateway_unconfigured');
});

it('creates a paypal payment and returns the hosted approval url', function () {
    config([
        'payment.paypal.client_id' => 'cid_test',
        'payment.paypal.client_secret' => 'secret_test',
    ]);
    activeMethod(PaymentMethodEnum::PAYPAL);
    $user = User::factory()->create();
    $this->actingAs($user);
    Currency::factory()->create([
        'id' => 2,
        CurrencySchema::CODE => 'USD',
        CurrencySchema::DECIMAL_PLACES => 2,
    ]);
    $order = payableOrder($user);
    // The PayPal order id doubles as the stored transaction_id the webhook
    // matches on.
    $client = Mockery::mock(PaypalClient::class);
    $client->shouldReceive('createOrder')->once()->andReturn((object) [
        'id' => '5O190127TN3647153',
        'links' => [
            (object) ['rel' => 'self', 'href' => 'https://api-m.sandbox.paypal.com/v2/checkout/orders/5O190127TN3647153'],
            (object) ['rel' => 'approve', 'href' => 'https://www.sandbox.paypal.com/checkoutnow?token=5O190127TN3647153'],
        ],
    ]);
    $this->app->instance(PaypalClient::class, $client);

    $response = $this->postJson($this->baseUrl('/user/process'), [
        'order_code' => $order->{OrderSchema::ORDER_CODE},
        'method' => 'paypal',
    ])->assertOk();

    $payment = Payment::query()->find($response->json('data.payment_id'));

    expect($response->json('data.payment_url'))->toBe('https://www.sandbox.paypal.com/checkoutnow?token=5O190127TN3647153')
        ->and($payment)->not->toBeNull()
        ->and($payment->{PaymentSchema::TRANSACTION_ID})->toBe('5O190127TN3647153')
        ->and($payment->{PaymentSchema::STATUS})->toBe(PaymentStatusEnum::PENDING)
        ->and($payment->{PaymentSchema::METHOD})->toBe(PaymentMethodEnum::PAYPAL);
});

it('creates an adyen payment and returns the hosted payment link url', function () {
    config([
        'payment.adyen.api_key' => 'AQE1hmfxKIPuJvh5BA',
        'payment.adyen.merchant_account' => 'BaxelaECOM',
        'payment.adyen.return_url' => 'https://shop.test/en/payment/return?order_code={order_code}',
    ]);
    activeMethod(PaymentMethodEnum::ADYEN);
    $user = User::factory()->create();
    $this->actingAs($user);
    Currency::factory()->create([
        'id' => 2,
        CurrencySchema::CODE => 'USD',
        CurrencySchema::DECIMAL_PLACES => 2,
    ]);
    $order = payableOrder($user);
    // The merchant reference (our payment id) doubles as the stored
    // transaction_id the webhook matches on.
    $checkout = Mockery::mock(AdyenCheckout::class);
    $checkout->shouldReceive('createPaymentLink')->once()->andReturn((object) [
        'id' => 'PLFF741A32D9E3F5B',
        'url' => 'https://checkout-test.adyen.com/link/PLFF741A32D9E3F5B',
        'status' => 'active',
    ]);
    $this->app->instance(AdyenCheckout::class, $checkout);

    $response = $this->postJson($this->baseUrl('/user/process'), [
        'order_code' => $order->{OrderSchema::ORDER_CODE},
        'method' => 'adyen',
    ])->assertOk();

    $payment = Payment::query()->find($response->json('data.payment_id'));

    expect($response->json('data.payment_url'))->toBe('https://checkout-test.adyen.com/link/PLFF741A32D9E3F5B')
        ->and($payment)->not->toBeNull()
        ->and($payment->{PaymentSchema::TRANSACTION_ID})->toBe((string) $payment->id)
        ->and($payment->{PaymentSchema::STATUS})->toBe(PaymentStatusEnum::PENDING)
        ->and($payment->{PaymentSchema::METHOD})->toBe(PaymentMethodEnum::ADYEN);
});

it('rejects an adyen payment when the gateway is not configured', function () {
    config([
        'payment.adyen.api_key' => null,
        'payment.adyen.merchant_account' => null,
    ]);
    activeMethod(PaymentMethodEnum::ADYEN);
    $user = User::factory()->create();
    $this->actingAs($user);
    $order = payableOrder($user);

    $this->postJson($this->baseUrl('/user/process'), [
        'order_code' => $order->{OrderSchema::ORDER_CODE},
        'method' => 'adyen',
    ])->assertStatus(400)->assertJsonPath('code', 'payment.process.gateway_unconfigured');
});

it('creates a nowpayments payment and returns the hosted invoice url', function () {
    config([
        'payment.nowpayments.api_key' => 'NP-API-KEY',
        'payment.nowpayments.ipn_secret' => 'np-ipn-test',
        'payment.nowpayments.success_url' => 'https://shop.test/en/payment/return?order_code={order_code}',
        'payment.nowpayments.cancel_url' => 'https://shop.test/en/payment/return?order_code={order_code}&status=cancel',
    ]);
    activeMethod(PaymentMethodEnum::NOWPAYMENTS);
    $user = User::factory()->create();
    $this->actingAs($user);
    Currency::factory()->create([
        'id' => 2,
        CurrencySchema::CODE => 'USD',
        CurrencySchema::DECIMAL_PLACES => 2,
    ]);
    $order = payableOrder($user);
    // The echoed order reference (our payment id) doubles as the stored
    // transaction_id the webhook matches on.
    $client = Mockery::mock(NowpaymentsClient::class);
    $client->shouldReceive('createInvoice')->once()->andReturn((object) [
        'id' => 4607606111,
        'invoice_url' => 'https://nowpayments.io/pay/i4607606111',
        'status' => 'waiting',
    ]);
    $this->app->instance(NowpaymentsClient::class, $client);

    $response = $this->postJson($this->baseUrl('/user/process'), [
        'order_code' => $order->{OrderSchema::ORDER_CODE},
        'method' => 'nowpayments',
    ])->assertOk();

    $payment = Payment::query()->find($response->json('data.payment_id'));

    expect($response->json('data.payment_url'))->toBe('https://nowpayments.io/pay/i4607606111')
        ->and($payment)->not->toBeNull()
        ->and($payment->{PaymentSchema::TRANSACTION_ID})->toBe((string) $payment->id)
        ->and($payment->{PaymentSchema::STATUS})->toBe(PaymentStatusEnum::PENDING)
        ->and($payment->{PaymentSchema::METHOD})->toBe(PaymentMethodEnum::NOWPAYMENTS);
});

it('rejects a nowpayments payment when the gateway is not configured', function () {
    config([
        'payment.nowpayments.api_key' => null,
        'payment.nowpayments.ipn_secret' => null,
    ]);
    activeMethod(PaymentMethodEnum::NOWPAYMENTS);
    $user = User::factory()->create();
    $this->actingAs($user);
    $order = payableOrder($user);

    $this->postJson($this->baseUrl('/user/process'), [
        'order_code' => $order->{OrderSchema::ORDER_CODE},
        'method' => 'nowpayments',
    ])->assertStatus(400)->assertJsonPath('code', 'payment.process.gateway_unconfigured');
});

it('creates a mercadopago payment and returns the hosted preference url', function () {
    config([
        'payment.mercadopago.access_token' => 'MP-ACCESS-TOKEN',
        'payment.mercadopago.webhook_secret' => 'mp-webhook-test',
        'payment.mercadopago.success_url' => 'https://shop.test/en/payment/return?order_code={order_code}',
        'payment.mercadopago.pending_url' => 'https://shop.test/en/payment/return?order_code={order_code}',
        'payment.mercadopago.failure_url' => 'https://shop.test/en/payment/return?order_code={order_code}&status=cancel',
    ]);
    activeMethod(PaymentMethodEnum::MERCADOPAGO);
    $user = User::factory()->create();
    $this->actingAs($user);
    Currency::factory()->create([
        'id' => 2,
        CurrencySchema::CODE => 'BRL',
        CurrencySchema::DECIMAL_PLACES => 2,
    ]);
    $order = payableOrder($user);
    // The external reference (our payment id) doubles as the stored
    // transaction_id the webhook matches on.
    $client = Mockery::mock(MercadopagoClient::class);
    $client->shouldReceive('createPreference')->once()->andReturn((object) [
        'id' => 'pref_1',
        'init_point' => 'https://www.mercadopago.com.br/checkout/v1/redirect?pref_id=pref_1',
        'sandbox_init_point' => 'https://sandbox.mercadopago.com.br/checkout/v1/redirect?pref_id=pref_1',
    ]);
    $this->app->instance(MercadopagoClient::class, $client);

    $response = $this->postJson($this->baseUrl('/user/process'), [
        'order_code' => $order->{OrderSchema::ORDER_CODE},
        'method' => 'mercadopago',
    ])->assertOk();

    $payment = Payment::query()->find($response->json('data.payment_id'));

    expect($response->json('data.payment_url'))->toBe('https://sandbox.mercadopago.com.br/checkout/v1/redirect?pref_id=pref_1')
        ->and($payment)->not->toBeNull()
        ->and($payment->{PaymentSchema::TRANSACTION_ID})->toBe((string) $payment->id)
        ->and($payment->{PaymentSchema::STATUS})->toBe(PaymentStatusEnum::PENDING)
        ->and($payment->{PaymentSchema::METHOD})->toBe(PaymentMethodEnum::MERCADOPAGO);
});

it('rejects a mercadopago payment when the gateway is not configured', function () {
    config([
        'payment.mercadopago.access_token' => null,
        'payment.mercadopago.webhook_secret' => null,
    ]);
    activeMethod(PaymentMethodEnum::MERCADOPAGO);
    $user = User::factory()->create();
    $this->actingAs($user);
    $order = payableOrder($user);

    $this->postJson($this->baseUrl('/user/process'), [
        'order_code' => $order->{OrderSchema::ORDER_CODE},
        'method' => 'mercadopago',
    ])->assertStatus(400)->assertJsonPath('code', 'payment.process.gateway_unconfigured');
});
