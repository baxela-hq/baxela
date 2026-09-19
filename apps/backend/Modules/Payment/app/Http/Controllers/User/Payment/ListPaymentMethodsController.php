<?php

namespace Modules\Payment\Http\Controllers\User\Payment;

use App\Http\Controllers\Controller;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Payment\Actions\User\Payment\ListPaymentMethodsAction;
use Modules\Payment\Transformers\User\Payment\PaymentMethodResource;

class ListPaymentMethodsController extends Controller
{
    public function __construct(protected ListPaymentMethodsAction $action) {}

    public function __invoke(): AnonymousResourceCollection
    {
        return PaymentMethodResource::collection($this->action->handle());
    }
}
