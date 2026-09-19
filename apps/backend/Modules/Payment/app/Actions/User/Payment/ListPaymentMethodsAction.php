<?php

namespace Modules\Payment\Actions\User\Payment;

use Modules\Payment\Schemas\Payment\PaymentMethodEnum;

class ListPaymentMethodsAction
{
    /**
     * Methods with a registered, configured driver — the storefront's source
     * of truth for which payment methods can be offered at checkout.
     *
     * @return array<int, PaymentMethodEnum>
     */
    public function handle(): array
    {
        $drivers = (array) config('payment.drivers');

        return array_values(array_filter(
            PaymentMethodEnum::cases(),
            fn (PaymentMethodEnum $method): bool => array_key_exists($method->value, $drivers)
                && $this->isConfigured($method),
        ));
    }

    /**
     * A registered driver without its credentials cannot serve payments —
     * hide it rather than offer a method that fails on every attempt.
     */
    private function isConfigured(PaymentMethodEnum $method): bool
    {
        return match ($method) {
            PaymentMethodEnum::MANUAL => true,
            PaymentMethodEnum::STRIPE => (bool) config('payment.stripe.secret'),
            default => false,
        };
    }
}
