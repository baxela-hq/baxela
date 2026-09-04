<?php

namespace Modules\Order\Http\Controllers\User\Order;

use App\Http\Controllers\Controller;
use Modules\Order\Actions\User\Order\CancelOrderAction;
use Modules\Order\Transformers\User\Order\OrderResource;

class CancelOrderController extends Controller
{
    public function __construct(protected CancelOrderAction $action) {}

    public function __invoke(string $id): OrderResource
    {
        return OrderResource::make($this->action->handle($id));
    }
}
