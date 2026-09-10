<?php

use Modules\Payment\Gateways\Drivers\ManualPaymentDriver;

return [
    // Key: PaymentMethodEnum value. Drivers without an entry (stripe, paypal)
    // fail with payment.process.method_not_supported until implemented.
    'drivers' => [
        'manual' => ManualPaymentDriver::class,
    ],

    // Per-IP attempts per minute on the machine-to-machine webhook endpoints.
    // Payment gateways retry in bursts, so keep this generous.
    'rate_limit' => [
        'webhook' => env('PAYMENT_WEBHOOK_RATE_LIMIT', 60),
    ],
];
