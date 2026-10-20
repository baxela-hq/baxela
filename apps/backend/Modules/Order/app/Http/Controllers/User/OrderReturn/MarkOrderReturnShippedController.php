<?php

namespace Modules\Order\Http\Controllers\User\OrderReturn;

use App\Http\Controllers\Controller;
use Modules\Order\Actions\User\OrderReturn\UpdateOwnOrderReturnAction;
use Modules\Order\Http\Requests\User\OrderReturn\MarkOrderReturnShippedRequest;
use Modules\Order\Transformers\User\OrderReturn\OrderReturnResource;

class MarkOrderReturnShippedController extends Controller
{
    public function __construct(protected UpdateOwnOrderReturnAction $action) {}

    public function __invoke(string $id, MarkOrderReturnShippedRequest $request): OrderReturnResource
    {
        return OrderReturnResource::make(
            $this->action->shipped($id, $request->validated())
        );
    }
}
