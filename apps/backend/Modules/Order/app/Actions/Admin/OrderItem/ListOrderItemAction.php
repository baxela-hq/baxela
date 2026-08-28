<?php

namespace Modules\Order\Actions\Admin\OrderItem;

use Modules\Order\Models\OrderItem;
use Modules\Order\Schemas\OrderItem\OrderItemSchema;

class ListOrderItemAction
{
    public function __construct(protected OrderItem $model) {}

    public function handle(string $orderId)
    {
        return $this->model->where([OrderItemSchema::ORDER_ID => $orderId])->get();
    }
}
