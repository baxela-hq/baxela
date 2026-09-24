<?php

namespace Modules\Payment\Http\Controllers\Admin\Payment;

use App\Http\Controllers\Controller;
use Modules\Payment\Actions\Admin\Payment\UpdatePaymentMethodAction;
use Modules\Payment\Http\Requests\Admin\Payment\PaymentMethodRequest;
use Modules\Payment\Transformers\Admin\Payment\PaymentMethodResource;

class UpdatePaymentMethodController extends Controller
{
    public function __construct(protected UpdatePaymentMethodAction $action) {}

    public function __invoke(string $id, PaymentMethodRequest $request): PaymentMethodResource
    {
        return PaymentMethodResource::make($this->action->handle($id, $request->validated()));
    }
}
