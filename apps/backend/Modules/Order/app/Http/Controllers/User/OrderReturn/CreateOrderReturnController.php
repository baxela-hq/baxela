<?php

namespace Modules\Order\Http\Controllers\User\OrderReturn;

use App\Http\Controllers\Controller;
use Modules\Order\Actions\User\OrderReturn\CreateOrderReturnAction;
use Modules\Order\Http\Requests\User\OrderReturn\CreateOrderReturnRequest;
use Modules\Order\Transformers\User\OrderReturn\OrderReturnResource;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class CreateOrderReturnController extends Controller
{
    public function __construct(protected CreateOrderReturnAction $action) {}

    public function __invoke(string $code, CreateOrderReturnRequest $request): JsonResponse
    {
        return (new OrderReturnResource($this->action->handle($code, $request->validated())))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
