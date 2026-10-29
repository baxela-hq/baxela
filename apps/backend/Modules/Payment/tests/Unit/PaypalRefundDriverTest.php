<?php

use Modules\Core\Contracts\Gateways\Payment\DTOs\PaymentRefundInput;
use Modules\Payment\Exceptions\PaymentException;
use Modules\Payment\Gateways\AdyenCheckout;
use Modules\Payment\Gateways\Drivers\AdyenPaymentDriver;
use Modules\Payment\Gateways\Drivers\PaypalPaymentDriver;
use Modules\Payment\Gateways\PaypalClient;
use Modules\Payment\Tests\Feature\HelperTrait;

uses(HelperTrait::class);

function paypalRefundInput(): PaymentRefundInput
{
    return new PaymentRefundInput(
        payment_id: 1,
        order_id: 2,
        transaction_id: 'PAYPAL-ORDER-1',
        amount: 10.5,
        currency: 'USD',
        currency_decimal_places: 2,
        idempotency_key: 'refund-uuid-1',
    );
}

it('refunds the completed capture with a decimal string and the request id', function () {
    $client = Mockery::mock(PaypalClient::class);
    $client->shouldReceive('getOrder')->once()->with('PAYPAL-ORDER-1')->andReturn((object) [
        'purchase_units' => [(object) ['payments' => (object) ['captures' => [
            (object) ['id' => 'CAP-1', 'status' => 'COMPLETED'],
            (object) ['id' => 'CAP-2', 'status' => 'PENDING'],
        ]]]],
    ]);

    $client->shouldReceive('refundCapture')->once()->with(
        'CAP-1',
        ['amount' => ['currency_code' => 'USD', 'value' => '10.50']],
        'refund-uuid-1',
    )->andReturn((object) ['id' => 'REF-9']);

    $driver = new PaypalPaymentDriver($client);

    expect($driver->refund(paypalRefundInput())->transaction_id)->toBe('REF-9');
});

it('refuses an order without a completed capture', function () {
    $client = Mockery::mock(PaypalClient::class);
    $client->shouldReceive('getOrder')->once()->andReturn((object) [
        'purchase_units' => [(object) ['payments' => (object) ['captures' => []]]],
    ]);

    (new PaypalPaymentDriver($client))->refund(paypalRefundInput());
})->throws(PaymentException::class, 'payment.refund.not_supported');

it('fails loudly on drivers without refund support', function () {
    $adyen = new AdyenPaymentDriver(Mockery::mock(AdyenCheckout::class));

    $adyen->refund(paypalRefundInput());
})->throws(PaymentException::class, 'payment.refund.not_supported');
