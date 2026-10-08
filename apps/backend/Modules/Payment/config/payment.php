<?php

use Modules\Payment\Gateways\Drivers\AdyenPaymentDriver;
use Modules\Payment\Gateways\Drivers\ManualPaymentDriver;
use Modules\Payment\Gateways\Drivers\NowpaymentsPaymentDriver;
use Modules\Payment\Gateways\Drivers\PaypalPaymentDriver;
use Modules\Payment\Gateways\Drivers\StripePaymentDriver;

return [
    // Key: PaymentMethodEnum value. Drivers without an entry fail with
    // payment.process.method_not_supported.
    'drivers' => [
        'manual' => ManualPaymentDriver::class,
        'stripe' => StripePaymentDriver::class,
        'paypal' => PaypalPaymentDriver::class,
        'adyen' => AdyenPaymentDriver::class,
        'nowpayments' => NowpaymentsPaymentDriver::class,
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

    // Automatic-capture merchant accounts only: with manual capture an
    // AUTHORISATION success is authorised-but-uncaptured and the driver
    // would settle orders prematurely.
    'adyen' => [
        // test|live — picks the Checkout API base URL.
        'env' => env('ADYEN_ENV', 'test'),
        'api_key' => env('ADYEN_API_KEY'),
        'merchant_account' => env('ADYEN_MERCHANT_ACCOUNT'),
        // Standard-webhook HMAC key from the Customer Area (base64).
        'hmac_key' => env('ADYEN_HMAC_KEY'),
        // Storefront return page; {order_code} is replaced with the order's
        // public code.
        'return_url' => env('ADYEN_RETURN_URL', 'http://localhost:3000/en/payment/return?order_code={order_code}'),
    ],

    'nowpayments' => [
        // sandbox|live — picks the NowPayments API base URL.
        'env' => env('NOWPAYMENTS_ENV', 'sandbox'),
        'api_key' => env('NOWPAYMENTS_API_KEY'),
        // IPN secret from account settings; verifies x-nowpayments-sig.
        'ipn_secret' => env('NOWPAYMENTS_IPN_SECRET'),
        // Optional per-invoice IPN callback pointing at
        // POST /api/v1/payment/webhook/nowpayments; when empty the
        // account-level setting is used.
        'ipn_callback_url' => env('NOWPAYMENTS_IPN_CALLBACK_URL'),
        // Storefront return pages; {order_code} is replaced with the order's
        // public code.
        'success_url' => env('NOWPAYMENTS_SUCCESS_URL', 'http://localhost:3000/en/payment/return?order_code={order_code}'),
        'cancel_url' => env('NOWPAYMENTS_CANCEL_URL', 'http://localhost:3000/en/payment/return?order_code={order_code}&status=cancel'),
    ],

    // Per-IP attempts per minute on the machine-to-machine webhook endpoints.
    // Payment gateways retry in bursts, so keep this generous.
    'rate_limit' => [
        'webhook' => env('PAYMENT_WEBHOOK_RATE_LIMIT', 60),
    ],
];
