<?php

namespace Modules\Payment\Http\Controllers\User\Payment;

use App\Http\Controllers\Controller;
use Modules\Payment\Actions\User\Payment\CreatePaymentAction;
use Modules\Payment\Http\Requests\User\Payment\PaymentRequest;
use Modules\Payment\Transformers\User\Payment\PaymentResource;

class CreatePaymentController extends Controller
{
    public function __construct(protected CreatePaymentAction $action) {}

    public function __invoke(PaymentRequest $request): PaymentResource
    {
        return PaymentResource::make($this->action->handle($request));
    }
}
