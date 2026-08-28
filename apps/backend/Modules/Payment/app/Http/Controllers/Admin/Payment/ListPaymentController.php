<?php

namespace Modules\Payment\Http\Controllers\Admin\Payment;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Payment\Actions\Admin\Payment\ListPaymentAction;
use Modules\Payment\Transformers\Admin\Payment\PaymentResource;

class ListPaymentController extends Controller
{
    public function __construct(protected ListPaymentAction $action) {}

    public function __invoke(Request $request): AnonymousResourceCollection
    {
        return PaymentResource::collection($this->action->handle());
    }
}
