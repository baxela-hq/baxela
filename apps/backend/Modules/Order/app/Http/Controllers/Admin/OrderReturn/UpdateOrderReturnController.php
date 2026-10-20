<?php

namespace Modules\Order\Http\Controllers\Admin\OrderReturn;

use App\Http\Controllers\Controller;
use Modules\Order\Actions\Admin\OrderReturn\UpdateOrderReturnAction;
use Modules\Order\Http\Requests\Admin\OrderReturn\UpdateOrderReturnRequest;
use Modules\Order\Transformers\Admin\OrderReturn\OrderReturnResource;

class UpdateOrderReturnController extends Controller
{
    public function __construct(protected UpdateOrderReturnAction $action) {}

    public function __invoke(string $id, UpdateOrderReturnRequest $request): OrderReturnResource
    {
        return OrderReturnResource::make($this->action->handle($id, $request->validated()));
    }
}
