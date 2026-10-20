<?php

namespace Modules\Order\Actions\User\OrderReturn;

use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\Events\Order\OrderReturnRequestedEvent;
use Modules\Core\Utils\Auth;
use Modules\Core\Utils\Locale;
use Modules\Order\Exceptions\OrderException;
use Modules\Order\Models\Order;
use Modules\Order\Models\OrderReturn;
use Modules\Order\Schemas\Order\OrderPaymentStatusEnum;
use Modules\Order\Schemas\Order\OrderSchema;
use Modules\Order\Schemas\Order\OrderStatusEnum;
use Modules\Order\Schemas\OrderReturn\OrderReturnSchema;
use Modules\Order\Schemas\OrderReturn\OrderReturnStatusEnum;

class CreateOrderReturnAction
{
    /**
     * Whole-order return request. Eligibility is checked under a lock on
     * the order row so two concurrent requests can never both claim the
     * single open-return slot.
     *
     * @param  array{reason: string, note?: string|null}  $data
     *
     * @throws OrderException
     */
    public function handle(string $orderCode, array $data): OrderReturn
    {
        $return = DB::transaction(function () use ($orderCode, $data): OrderReturn {
            $order = Order::query()
                ->where(OrderSchema::ORDER_CODE, $orderCode)
                ->where(OrderSchema::USER_ID, Auth::id())
                ->lockForUpdate()
                ->first();

            if (is_null($order)) {
                throw OrderException::returnNotFound();
            }

            // Fully refunded orders have nothing left to return against;
            // unpaid or unshipped ones have nothing in the customer's hands
            $eligible = in_array($order->{OrderSchema::PAYMENT_STATUS}, [
                OrderPaymentStatusEnum::PAID,
                OrderPaymentStatusEnum::PARTIALLY_REFUNDED,
            ], true)
                && in_array($order->{OrderSchema::STATUS}, [
                    OrderStatusEnum::SHIPPED,
                    OrderStatusEnum::COMPLETED,
                ], true)
                && $this->withinWindow($order)
                && ! OrderReturn::query()
                    ->where(OrderReturnSchema::ORDER_ID, $order->{OrderSchema::ID})
                    ->whereIn(OrderReturnSchema::STATUS, OrderReturnStatusEnum::open())
                    ->exists();

            if (! $eligible) {
                throw OrderException::returnIneligible();
            }

            return OrderReturn::query()->create([
                OrderReturnSchema::ORDER_ID => $order->{OrderSchema::ID},
                OrderReturnSchema::USER_ID => Auth::id(),
                OrderReturnSchema::STATUS => OrderReturnStatusEnum::REQUESTED,
                OrderReturnSchema::REASON => $data[OrderReturnSchema::REASON],
                OrderReturnSchema::NOTE => $data[OrderReturnSchema::NOTE] ?? null,
            ]);
        });

        event(OrderReturnRequestedEvent::fill([
            OrderReturnSchema::ID => (int) $return->{OrderReturnSchema::ID},
            OrderReturnSchema::ORDER_ID => (int) $return->{OrderReturnSchema::ORDER_ID},
            OrderSchema::ORDER_CODE => $orderCode,
            OrderSchema::USER_ID => (int) $return->{OrderReturnSchema::USER_ID},
            OrderReturnSchema::STATUS => $return->{OrderReturnSchema::STATUS}->value,
            OrderReturnSchema::REASON => $return->{OrderReturnSchema::REASON},
            'locale' => Locale::fromRequest(),
        ]));

        return $return;
    }

    /**
     * The window runs from payment (falling back to placement) — a
     * shipped order cannot have its window expire before arrival.
     */
    private function withinWindow(Order $order): bool
    {
        $anchor = $order->{OrderSchema::PAID_AT} ?? $order->{OrderSchema::CREATED_AT};

        return $anchor->gte(
            now()->subDays((int) config('order.returns.window_days', 14))
        );
    }
}
