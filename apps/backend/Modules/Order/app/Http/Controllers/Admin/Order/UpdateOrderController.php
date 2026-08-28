<?php

namespace Modules\Order\Http\Controllers\Admin\Order;

use App\Http\Controllers\Controller;
use Modules\Order\Actions\Admin\Order\UpdateOrderAction;
use Modules\Order\Http\Requests\Admin\Order\OrderRequest;
use Modules\Order\Transformers\Admin\Order\OrderResource;

class UpdateOrderController extends Controller
{
    public function __construct(protected UpdateOrderAction $action) {}

    public function __invoke(string $orderId, OrderRequest $request): OrderResource
    {
        return OrderResource::make($this->action->handle($orderId, $request));
    }
}
