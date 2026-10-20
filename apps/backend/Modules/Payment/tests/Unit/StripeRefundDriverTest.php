<?php

use Mockery\MockInterface;
use Modules\Core\Contracts\Gateways\Payment\DTOs\PaymentRefundInput;
use Modules\Payment\Exceptions\PaymentException;
use Modules\Payment\Gateways\Drivers\StripePaymentDriver;
use Modules\Payment\Gateways\StripeCheckout;
use Modules\Payment\Tests\Feature\HelperTrait;
use Stripe\Checkout\Session;
use Stripe\Refund;

uses(HelperTrait::class);

beforeEach(function () {
    config(['payment.stripe.secret' => 'sk_test_x']);
});

function stripeRefundDriver(callable $configure): StripePaymentDriver
{
    $checkout = Mockery::mock(StripeCheckout::class);
    $configure($checkout);

    return new StripePaymentDriver($checkout);
}

function stripeSession(array $attributes): Session
{
    return Session::constructFrom($attributes);
}

/**
 * @param  array<string, mixed>  $overrides
 */
function refundInput(array $overrides = []): PaymentRefundInput
{
    $base = [
        'payment_id' => 1,
        'order_id' => 2,
        'transaction_id' => 'cs_test_123',
        'amount' => 10.0,
        'currency' => 'USD',
        'currency_decimal_places' => 2,
        'idempotency_key' => 'refund-uuid-1',
    ];

    return new PaymentRefundInput(...array_values(array_merge($base, $overrides)));
}

it('refunds through the session payment intent with minor units and the idempotency key', function () {
    $driver = stripeRefundDriver(function (MockInterface $checkout): void {
        $checkout->shouldReceive('retrieveSession')->once()->with('cs_test_123')
            ->andReturn(stripeSession(['id' => 'cs_test_123', 'payment_intent' => 'pi_123']));

        $checkout->shouldReceive('createRefund')->once()->with(
            ['payment_intent' => 'pi_123', 'amount' => 1000],
            'refund-uuid-1',
        )->andReturn(Refund::constructFrom(['id' => 're_123']));
    });

    $result = $driver->refund(refundInput());

    expect($result->transaction_id)->toBe('re_123');
});

it('refuses a session without a payment intent instead of guessing', function () {
    $driver = stripeRefundDriver(function (MockInterface $checkout): void {
        $checkout->shouldReceive('retrieveSession')->once()
            ->andReturn(stripeSession(['id' => 'cs_test_123', 'payment_intent' => null]));
    });

    $driver->refund(refundInput());
})->throws(PaymentException::class, 'payment.refund.not_supported');
