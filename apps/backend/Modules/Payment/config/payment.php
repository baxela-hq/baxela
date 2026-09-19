<?php

use Modules\Payment\Gateways\Drivers\ManualPaymentDriver;
use Modules\Payment\Gateways\Drivers\StripePaymentDriver;

return [
    // Key: PaymentMethodEnum value. Drivers without an entry (paypal) fail
    // with payment.process.method_not_supported until implemented.
    'drivers' => [
        'manual' => ManualPaymentDriver::class,
        'stripe' => StripePaymentDriver::class,
    ],

    'stripe' => [
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        // Storefront return page; {order_code} is replaced with the order's
        // public code.
        'success_url' => env('STRIPE_SUCCESS_URL', 'http://localhost:3000/en/payment/return?order_code={order_code}'),
        'cancel_url' => env('STRIPE_CANCEL_URL', 'http://localhost:3000/en/payment/return?order_code={order_code}&status=cancel'),
    ],

    // Per-IP attempts per minute on the machine-to-machine webhook endpoints.
    // Payment gateways retry in bursts, so keep this generous.
    'rate_limit' => [
        'webhook' => env('PAYMENT_WEBHOOK_RATE_LIMIT', 60),
    ],
];
