<?php

use Modules\Payment\Gateways\Drivers\ManualPaymentDriver;

return [
    // Key: PaymentMethodEnum value. Drivers without an entry (stripe, paypal)
    // fail with payment.process.method_not_supported until implemented.
    'drivers' => [
        'manual' => ManualPaymentDriver::class,
    ],
];
