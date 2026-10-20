<?php

namespace Modules\Payment\Http\Controllers\Admin\PaymentRefund;

use App\Http\Controllers\Controller;
use Modules\Payment\Actions\Admin\PaymentRefund\ResolveRefundAction;
use Modules\Payment\Http\Requests\Admin\PaymentRefund\ResolveRefundRequest;
use Modules\Payment\Transformers\Admin\PaymentRefund\PaymentRefundResource;

class ResolvePaymentRefundController extends Controller
{
    public function __construct(protected ResolveRefundAction $action) {}

    public function __invoke(string $id, ResolveRefundRequest $request): PaymentRefundResource
    {
        return PaymentRefundResource::make($this->action->handle($id, $request->validated()));
    }
}
