<?php

namespace Modules\Webhooks\Http\Controllers\Machine;

use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Order\Models\Order;
use Modules\Order\Schemas\Order\OrderSchema;
use Modules\Order\Transformers\Admin\Order\OrderResource;

class ShowMachineOrderController extends MachineController
{
    public function __invoke(string $id): JsonResource
    {
        return new OrderResource(
            Order::query()
                ->where(OrderSchema::USER_ID, $this->machineUserId())
                ->findOrFail($id)
                ->load(OrderSchema::RES_ITEMS, OrderSchema::RES_ADDRESSES),
        );
    }
}
