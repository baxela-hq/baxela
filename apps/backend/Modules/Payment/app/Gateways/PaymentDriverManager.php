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
}
