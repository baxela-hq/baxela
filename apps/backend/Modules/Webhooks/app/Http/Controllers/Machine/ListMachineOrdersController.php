<?php

namespace Modules\Webhooks\Http\Controllers\Machine;

use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Order\Models\Order;
use Modules\Order\Schemas\Order\OrderSchema;
use Modules\Order\Transformers\Admin\Order\OrderResource;

class ListMachineOrdersController extends MachineController
{
    public function __invoke(): AnonymousResourceCollection
    {
        return OrderResource::collection(
            Order::query()
                ->where(OrderSchema::USER_ID, $this->machineUserId())
                ->orderByDesc(OrderSchema::ID)
                ->jsonPaginate(),
        );
    }
}
