<?php

namespace Modules\Order\Http\Controllers\User\Order;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Order\Actions\User\Order\ShowOrderAction;
use Modules\Order\Transformers\User\Order\OrderResource;

class ShowOrderController extends Controller
{
    public function __construct(protected ShowOrderAction $action) {}

    public function __invoke(string $id, Request $request): OrderResource
    {
        return OrderResource::make($this->action->handle($id));
    }
}
