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
            default => false,
        };
    }
}
