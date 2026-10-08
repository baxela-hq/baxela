<?php

namespace Modules\Payment\Gateways;

use Modules\Core\Contracts\Gateways\Payment\PaymentDriverInterface;
use Modules\Payment\Exceptions\PaymentException;
use Modules\Payment\Schemas\Payment\PaymentMethodEnum;

class PaymentDriverManager
{
    /**
     * @throws PaymentException
     */
    public function forMethod(PaymentMethodEnum $method): PaymentDriverInterface
    {
        return $this->forName($method->value);
    }

    /**
     * @throws PaymentException
     */
    public function forName(string $name): PaymentDriverInterface
    {
        $driver = config("payment.drivers.{$name}");

        if (is_null($driver)) {
            throw PaymentException::methodNotSupported();
        }

        return app($driver);
    }

    public function isRegistered(PaymentMethodEnum $method): bool
    {
        return array_key_exists($method->value, (array) config('payment.drivers'));
    }

    /**
     * A registered driver without its credentials cannot serve payments —
     * hide it rather than offer a method that fails on every attempt.
     */
    public function isConfigured(PaymentMethodEnum $method): bool
    {
        return match ($method) {
            PaymentMethodEnum::MANUAL => true,
            PaymentMethodEnum::STRIPE => (bool) config('payment.stripe.secret'),
            PaymentMethodEnum::PAYPAL => (bool) config('payment.paypal.client_id')
                && (bool) config('payment.paypal.client_secret'),
            PaymentMethodEnum::ADYEN => (bool) config('payment.adyen.api_key')
                && (bool) config('payment.adyen.merchant_account'),
            PaymentMethodEnum::NOWPAYMENTS => (bool) config('payment.nowpayments.api_key')
                && (bool) config('payment.nowpayments.ipn_secret'),
            PaymentMethodEnum::MERCADOPAGO => (bool) config('payment.mercadopago.access_token')
                && (bool) config('payment.mercadopago.webhook_secret'),
            default => false,
        };
    }
}
