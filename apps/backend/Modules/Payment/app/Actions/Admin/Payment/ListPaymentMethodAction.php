<?php

namespace Modules\Payment\Actions\Admin\Payment;

use Modules\Payment\Models\PaymentMethod;
use Modules\Payment\Schemas\Payment\PaymentMethodSchema;

class ListPaymentMethodAction
{
    public function __construct(protected PaymentMethod $model) {}

    /**
     * All method rows, ordered by their admin-set position. A row is
     * lazily created for every registered driver so a newly added
     * gateway appears here (disabled) without a manual seed.
     *
     * @return array<int, PaymentMethod>
     */
    public function handle(): array
    {
        foreach (array_keys((array) config('payment.drivers')) as $method) {
            $this->model->query()->firstOrCreate(
                [PaymentMethodSchema::METHOD => $method],
                [
                    PaymentMethodSchema::IS_ACTIVE => false,
                    PaymentMethodSchema::SORT_ORDER => 0,
                ],
            );
        }

        return $this->model->query()
            ->orderBy(PaymentMethodSchema::SORT_ORDER)
            ->orderBy(PaymentMethodSchema::METHOD)
            ->get()
            ->all();
    }
}
