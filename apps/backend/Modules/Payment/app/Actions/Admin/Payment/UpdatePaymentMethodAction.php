<?php

namespace Modules\Payment\Actions\Admin\Payment;

use Modules\Payment\Exceptions\PaymentException;
use Modules\Payment\Gateways\PaymentDriverManager;
use Modules\Payment\Models\PaymentMethod;
use Modules\Payment\Schemas\Payment\PaymentMethodSchema;

class UpdatePaymentMethodAction
{
    public function __construct(protected PaymentMethod $model) {}

    /**
     * Activate/deactivate a method and set its checkout position.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws PaymentException
     */
    public function handle(string $id, array $data): PaymentMethod
    {
        $method = $this->model->query()->findOrFail($id);
        $methodEnum = $method->{PaymentMethodSchema::METHOD};

        // A row whose driver was removed can never reach the storefront;
        // refuse to pretend activating it does anything.
        if (! app(PaymentDriverManager::class)->isRegistered($methodEnum)) {
            throw PaymentException::methodNotSupported();
        }

        $method->fill([
            PaymentMethodSchema::IS_ACTIVE => $data[PaymentMethodSchema::IS_ACTIVE],
            PaymentMethodSchema::SORT_ORDER => $data[PaymentMethodSchema::SORT_ORDER],
        ])->save();

        return $method->refresh();
    }
}
