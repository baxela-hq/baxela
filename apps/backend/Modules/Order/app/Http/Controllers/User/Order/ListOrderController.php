<?php

namespace Modules\Order\Http\Controllers\User\Order;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Order\Actions\User\Order\ListOrderAction;
use Modules\Order\Transformers\User\Order\OrderResource;

class ListOrderController extends Controller
{
    public function __construct(protected ListOrderAction $action) {}

    public function __invoke(Request $request): AnonymousResourceCollection
    {
        return OrderResource::collection($this->action->handle());
    }
}
