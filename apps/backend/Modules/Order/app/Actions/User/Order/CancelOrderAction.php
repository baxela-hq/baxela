<?php

namespace Modules\Order\Actions\User\Order;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Core\Contracts\Events\Order\OrderCancelledEvent;
use Modules\Core\Utils\Auth;
use Modules\Order\Schemas\Order\OrderSchema;
use Modules\Order\Schemas\Order\OrderStatusEnum;

class CancelOrderAction extends AbstractOrderAction
{
    /**
     * A customer may only cancel while the order is still awaiting fulfilment;
     * later states go through the admin/refund flow.
     */
    private const array CANCELLABLE_STATUSES = [
        OrderStatusEnum::PENDING_PAYMENT,
        OrderStatusEnum::PAID,
    ];

    public function handle(string $id): Model
    {
        $order = $this->order
            ->where(OrderSchema::ID, $id)
            ->where(OrderSchema::USER_ID, Auth::id())
            ->firstOrFail();

        if (! in_array($order->{OrderSchema::STATUS}, self::CANCELLABLE_STATUSES, true)) {
            throw ValidationException::withMessages([
                OrderSchema::STATUS => __('The order can no longer be cancelled.'),
            ]);
        }

        return DB::transaction(function () use ($order): Model {
            $order->{OrderSchema::STATUS} = OrderStatusEnum::CANCELLED;
            $order->save();

            event(OrderCancelledEvent::fill([
                'id' => $order->{OrderSchema::ID},
                'user_id' => $order->{OrderSchema::USER_ID},
                'status' => $order->{OrderSchema::STATUS}->value,
            ]));

            return $order;
        });
    }
}
