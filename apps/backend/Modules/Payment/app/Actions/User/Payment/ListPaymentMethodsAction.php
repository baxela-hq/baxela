<?php

namespace Modules\Payment\Actions\User\Payment;

use Modules\Payment\Gateways\PaymentDriverManager;
use Modules\Payment\Models\PaymentMethod;
use Modules\Payment\Schemas\Payment\PaymentMethodEnum;
use Modules\Payment\Schemas\Payment\PaymentMethodSchema;

class ListPaymentMethodsAction
{
    /**
     * Methods with a registered, configured driver that an admin has
     * activated — the storefront's source of truth for which payment
     * methods can be offered at checkout, in the admin-set order. A
     * method without a row (a newly added driver) counts as disabled.
     *
     * @return array<int, PaymentMethodEnum>
     */
    public function handle(): array
    {
        $manager = app(PaymentDriverManager::class);

        return PaymentMethod::query()
            ->where(PaymentMethodSchema::IS_ACTIVE, true)
            ->orderBy(PaymentMethodSchema::SORT_ORDER)
            ->get()
            ->map(fn (PaymentMethod $row): PaymentMethodEnum => $row->{PaymentMethodSchema::METHOD})
            ->filter(fn (PaymentMethodEnum $method): bool => $manager->isRegistered($method)
                && $manager->isConfigured($method))
            ->values()
            ->all();
    }
}
