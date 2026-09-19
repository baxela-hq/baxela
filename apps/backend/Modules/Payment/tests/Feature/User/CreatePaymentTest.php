<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\Models\User;
use Modules\Core\Models\Currency;
use Modules\Core\Schemas\Currency\CurrencySchema;
use Modules\Order\Models\Order;
use Modules\Order\Schemas\Order\OrderPaymentStatusEnum;
use Modules\Order\Schemas\Order\OrderSchema;
use Modules\Order\Schemas\Order\OrderStatusEnum;
use Modules\Payment\Gateways\StripeCheckout;
use Modules\Payment\Models\Payment;
use Modules\Payment\Schemas\Payment\PaymentMethodEnum;
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

it('creates a pending manual payment for a payable order', function () {
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

it('rejects a method with no configured driver', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $order = payableOrder($user);

    $this->postJson($this->baseUrl('/user/process'), [
        'order_code' => $order->{OrderSchema::ORDER_CODE},
        'method' => 'paypal',
    ])->assertStatus(400)->assertJsonPath('code', 'payment.process.method_not_supported');
});

it('creates a stripe payment and returns the hosted checkout url', function () {
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
    $user = User::factory()->create();
    $this->actingAs($user);
    $order = payableOrder($user);

    $this->postJson($this->baseUrl('/user/process'), [
        'order_code' => $order->{OrderSchema::ORDER_CODE},
        'method' => 'stripe',
    ])->assertStatus(400)->assertJsonPath('code', 'payment.process.gateway_unconfigured');
});
