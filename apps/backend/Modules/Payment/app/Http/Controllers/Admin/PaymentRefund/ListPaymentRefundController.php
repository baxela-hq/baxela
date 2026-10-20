<?php

namespace Modules\Payment\Http\Controllers\Admin\PaymentRefund;

use App\Http\Controllers\Controller;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Payment\Actions\Admin\PaymentRefund\ListPaymentRefundsAction;
use Modules\Payment\Transformers\Admin\PaymentRefund\PaymentRefundResource;

class ListPaymentRefundController extends Controller
{
    public function __construct(protected ListPaymentRefundsAction $action) {}

    public function __invoke(string $id): AnonymousResourceCollection
    {
        return PaymentRefundResource::collection($this->action->handle($id));
    }
}
