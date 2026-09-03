<?php

namespace Modules\Order\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Core\Contracts\Events\Order\OrderCancelledEvent;
use Modules\Order\Models\Order;
use Modules\Order\Schemas\Order\OrderSchema;
use Modules\Order\Schemas\Order\OrderStatusEnum;

class CancelExpiredOrdersCommand extends Command
{
    protected $signature = 'order:cancel-expired';

    protected $description = 'Cancel pending-payment orders past their expires_at and release their reserved stock';

    public function handle(): int
    {
        $expiredOrders = Order::query()
            ->where(OrderSchema::STATUS, OrderStatusEnum::PENDING_PAYMENT)
            ->where(OrderSchema::EXPIRES_AT, '<', now())
            ->get();

        foreach ($expiredOrders as $order) {
            DB::transaction(function () use ($order): void {
                $order->{OrderSchema::STATUS} = OrderStatusEnum::CANCELLED;
                $order->save();

                event(OrderCancelledEvent::fill([
                    'id' => $order->{OrderSchema::ID},
                    'user_id' => $order->{OrderSchema::USER_ID},
                    'status' => $order->{OrderSchema::STATUS}->value,
                ]));
            });
        }

        if ($expiredOrders->isNotEmpty()) {
            Log::info('Cancelled {count} expired order(s).', ['count' => $expiredOrders->count()]);
        }

        return self::SUCCESS;
    }
}
