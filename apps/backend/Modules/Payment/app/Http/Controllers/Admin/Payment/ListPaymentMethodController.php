<?php

namespace Modules\Payment\Http\Controllers\Admin\Payment;

use App\Http\Controllers\Controller;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Payment\Actions\Admin\Payment\ListPaymentMethodAction;
use Modules\Payment\Transformers\Admin\Payment\PaymentMethodResource;

class ListPaymentMethodController extends Controller
{
    public function __construct(protected ListPaymentMethodAction $action) {}

    public function __invoke(): AnonymousResourceCollection
    {
        return PaymentMethodResource::collection($this->action->handle());
    }
}
