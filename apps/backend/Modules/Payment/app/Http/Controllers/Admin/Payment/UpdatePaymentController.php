<?php

namespace Modules\Payment\Http\Controllers\Admin\Payment;

use App\Http\Controllers\Controller;
use Modules\Payment\Actions\Admin\Payment\UpdatePaymentAction;
use Modules\Payment\Http\Requests\Admin\Payment\UpdatePaymentRequest;
use Modules\Payment\Transformers\Admin\Payment\PaymentResource;

class UpdatePaymentController extends Controller
{
    public function __construct(protected UpdatePaymentAction $action) {}

    public function __invoke(string $id, UpdatePaymentRequest $request): PaymentResource
    {
        return PaymentResource::make($this->action->handle($id, $request->validated()));
    }
}
