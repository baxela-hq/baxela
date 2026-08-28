<?php

namespace Modules\Order\Http\Controllers\Admin\Order;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Order\Actions\Admin\Order\ListOrderAction;
use Modules\Order\Transformers\Admin\Order\OrderResource;

class ListOrderController extends Controller
{
    public function __construct(protected ListOrderAction $action) {}

    public function __invoke(Request $request)
    {
        return OrderResource::collection($this->action->handle());
    }
}
