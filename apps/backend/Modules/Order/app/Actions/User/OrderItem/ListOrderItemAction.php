<?php

namespace Modules\Order\Actions\User\OrderItem;

use Modules\Core\Utils\Auth;
use Modules\Order\Models\Order;
use Modules\Order\Schemas\Order\OrderSchema;

class ListOrderItemAction
{
    public function handle(string $orderId)
    {
        $order = Order::where([
            OrderSchema::ID => $orderId,
            OrderSchema::USER_ID => Auth::id(),
        ])->firstOrfail();

        return $order->items;
    }
}
