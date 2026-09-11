<?php

namespace Modules\Order\Actions\Admin\Order;

use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\Events\Order\OrderCancelledEvent;
use Modules\Core\Contracts\Events\Order\OrderCompletedEvent;
use Modules\Core\Contracts\Events\Order\OrderPaidEvent;
use Modules\Core\Contracts\Events\Order\OrderRefundedEvent;
use Modules\Core\Contracts\Events\Order\OrderShippedEvent;
use Modules\Order\Exceptions\OrderException;
use Modules\Order\Http\Requests\Admin\Order\OrderRequest;
use Modules\Order\Models\Order;
use Modules\Order\Schemas\Order\OrderPaymentStatusEnum;
use Modules\Order\Schemas\Order\OrderSchema;
use Modules\Order\Schemas\Order\OrderStatusEnum;

class UpdateOrderAction extends AbstractOrderAction
{
    /**
     * @throws OrderException
     */
    public function handle(string $id, OrderRequest $request): Order
    {
        /** @var Order $record */
        $record = $this->model->findOrFail($id);

        $data = $request->validated();

        $explicitStatus = ! is_null($data[OrderSchema::STATUS] ?? null);
        $status = $explicitStatus
            ? OrderStatusEnum::from($data[OrderSchema::STATUS])
            : $record->{OrderSchema::STATUS};

        $paymentStatus = ! is_null($data[OrderSchema::PAYMENT_STATUS] ?? null)
            ? OrderPaymentStatusEnum::from($data[OrderSchema::PAYMENT_STATUS])
            : $record->{OrderSchema::PAYMENT_STATUS};

        $paymentJustSucceeded = $paymentStatus === OrderPaymentStatusEnum::PAID
            && $record->{OrderSchema::PAYMENT_STATUS} !== OrderPaymentStatusEnum::PAID;

        // Paying an unpaid, still-pending order starts fulfilment — unless the
        // request pins an explicit fulfillment status of its own.
        if ($paymentJustSucceeded
            && ! $explicitStatus
            && $status === OrderStatusEnum::PENDING) {
            $status = OrderStatusEnum::PROCESSING;
        }

        if (! $record->{OrderSchema::STATUS}->canTransitionTo($status)) {
            throw OrderException::invalidStatusTransition();
        }

        if (! $record->{OrderSchema::PAYMENT_STATUS}->canTransitionTo($paymentStatus)) {
            throw OrderException::invalidPaymentStatusTransition();
        }

        $statusChanged = $record->{OrderSchema::STATUS} !== $status;
        $paymentStatusChanged = $record->{OrderSchema::PAYMENT_STATUS} !== $paymentStatus;

        DB::transaction(function () use ($record, $status, $paymentStatus, $paymentJustSucceeded): void {
            if ($statusChanged) {
                $record->{OrderSchema::STATUS} = $status;
            }

            if ($paymentJustSucceeded) {
                $record->{OrderSchema::PAID_AT} = now();
            }

            if ($paymentStatusChanged) {
                $record->{OrderSchema::PAYMENT_STATUS} = $paymentStatus;
            }

            $record->save();
        });

        $this->dispatchTransitionEvents($record, $status, $statusChanged, $paymentStatus, $paymentStatusChanged);

        return $record->load(OrderSchema::RES_ITEMS);
    }

    private function dispatchTransitionEvents(
        Order $record,
        OrderStatusEnum $status,
        bool $statusChanged,
        OrderPaymentStatusEnum $paymentStatus,
        bool $paymentStatusChanged,
    ): void {
        $payload = [
            OrderSchema::ID => $record->{OrderSchema::ID},
            OrderSchema::USER_ID => $record->{OrderSchema::USER_ID},
            OrderSchema::STATUS => $status->value,
        ];

        if ($statusChanged) {
            $event = match ($status) {
                OrderStatusEnum::SHIPPED => OrderShippedEvent::class,
                OrderStatusEnum::COMPLETED => OrderCompletedEvent::class,
                OrderStatusEnum::CANCELLED => OrderCancelledEvent::class,
                default => null,
            };

            if (! is_null($event)) {
                event($event::fill($payload));
            }
        }

        if ($paymentStatusChanged) {
            $event = match ($paymentStatus) {
                OrderPaymentStatusEnum::PAID => OrderPaidEvent::class,
                OrderPaymentStatusEnum::REFUNDED => OrderRefundedEvent::class,
                default => null,
            };

            if (! is_null($event)) {
                event($event::fill($payload + [
                    OrderSchema::PAYMENT_STATUS => $paymentStatus->value,
                ]));
            }
        }
    }
}
