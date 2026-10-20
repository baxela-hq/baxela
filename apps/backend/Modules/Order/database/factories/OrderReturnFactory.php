<?php

namespace Modules\Order\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Order\Models\Order;
use Modules\Order\Models\OrderReturn;
use Modules\Order\Schemas\OrderReturn\OrderReturnReasonEnum;
use Modules\Order\Schemas\OrderReturn\OrderReturnSchema;
use Modules\Order\Schemas\OrderReturn\OrderReturnStatusEnum;

class OrderReturnFactory extends Factory
{
    protected $model = OrderReturn::class;

    public function definition(array $params = []): array
    {
        $order = $params[OrderReturnSchema::ORDER_ID] ?? Order::factory();

        return [
            OrderReturnSchema::ORDER_ID => $order instanceof Order ? $order->id : $order,
            OrderReturnSchema::USER_ID => $params[OrderReturnSchema::USER_ID]
                ?? $this->faker->randomDigitNotZero(),
            OrderReturnSchema::STATUS => $params[OrderReturnSchema::STATUS]
                ?? OrderReturnStatusEnum::REQUESTED,
            OrderReturnSchema::REASON => $params[OrderReturnSchema::REASON]
                ?? OrderReturnReasonEnum::OTHER,
            OrderReturnSchema::NOTE => $params[OrderReturnSchema::NOTE]
                ?? null,
            OrderReturnSchema::ADMIN_NOTE => $params[OrderReturnSchema::ADMIN_NOTE]
                ?? null,
            OrderReturnSchema::TRACKING_NUMBER => $params[OrderReturnSchema::TRACKING_NUMBER]
                ?? null,
            OrderReturnSchema::STOCK_RESTORED_AT => $params[OrderReturnSchema::STOCK_RESTORED_AT]
                ?? null,
        ];
    }
}
