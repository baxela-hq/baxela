<?php

namespace Modules\Order\Http\Controllers\Admin\Order;

use App\Http\Controllers\Controller;
use Modules\Order\Actions\Admin\Order\ShowOrderAction;
use Modules\Order\Transformers\Admin\Order\OrderResource;

class ShowOrderController extends Controller
{
    public function __construct(protected ShowOrderAction $action) {}

    public function __invoke(string $id): OrderResource
    {
        return new OrderResource($this->action->handle($id));
    }
}
