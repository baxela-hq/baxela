<?php

use Modules\Payment\Gateways\Drivers\ManualPaymentDriver;
use Modules\Payment\Gateways\Drivers\PaypalPaymentDriver;
use Modules\Payment\Gateways\Drivers\StripePaymentDriver;

return [
    // Key: PaymentMethodEnum value. Drivers without an entry fail with
    // payment.process.method_not_supported.
    'drivers' => [
        'manual' => ManualPaymentDriver::class,
        'stripe' => StripePaymentDriver::class,
        'paypal' => PaypalPaymentDriver::class,
    ],

    'stripe' => [
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        // Storefront return page; {order_code} is replaced with the order's
        // public code.
        'success_url' => env('STRIPE_SUCCESS_URL', 'http://localhost:3000/en/payment/return?order_code={order_code}'),
        'cancel_url' => env('STRIPE_CANCEL_URL', 'http://localhost:3000/en/payment/return?order_code={order_code}&status=cancel'),
    ],

    'paypal' => [
        // sandbox|live — picks the Orders v2 REST base URL.
        'mode' => env('PAYPAL_MODE', 'sandbox'),
        'client_id' => env('PAYPAL_CLIENT_ID'),
        'client_secret' => env('PAYPAL_CLIENT_SECRET'),
        'webhook_id' => env('PAYPAL_WEBHOOK_ID'),
        // Storefront return page; {order_code} is replaced with the order's
        // public code.
        'return_url' => env('PAYPAL_RETURN_URL', 'http://localhost:3000/en/payment/return?order_code={order_code}'),
        'cancel_url' => env('PAYPAL_CANCEL_URL', 'http://localhost:3000/en/payment/return?order_code={order_code}&status=cancel'),
    ],

    // Per-IP attempts per minute on the machine-to-machine webhook endpoints.
    // Payment gateways retry in bursts, so keep this generous.
    'rate_limit' => [
        'webhook' => env('PAYMENT_WEBHOOK_RATE_LIMIT', 60),
    ],
];
