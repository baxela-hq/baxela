<?php

namespace Modules\Payment\Http\Controllers\Admin\PaymentRefund;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Payment\Actions\Admin\PaymentRefund\CreateRefundAction;
use Modules\Payment\Http\Requests\Admin\PaymentRefund\CreateRefundRequest;
use Modules\Payment\Transformers\Admin\PaymentRefund\PaymentRefundResource;
use Symfony\Component\HttpFoundation\Response;

class CreatePaymentRefundController extends Controller
{
    public function __construct(protected CreateRefundAction $action) {}

    public function __invoke(string $id, CreateRefundRequest $request): JsonResponse
    {
        return (new PaymentRefundResource($this->action->handle($id, $request->validated())))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
