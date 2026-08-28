<?php

namespace Modules\Payment\Actions\Admin\Payment;

use Modules\Payment\Models\Payment;

class ListPaymentAction
{
    public function __construct(protected Payment $model) {}

    public function handle()
    {
        return $this->model
            ->paginate(15)
            ->withQueryString();
    }
}
