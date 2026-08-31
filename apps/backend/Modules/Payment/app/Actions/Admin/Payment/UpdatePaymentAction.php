<?php

namespace Modules\Payment\Actions\Admin\Payment;

use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\Events\Payment\PaymentFailedEvent;
use Modules\Core\Contracts\Events\Payment\PaymentSucceededEvent;
use Modules\Core\Contracts\Gateways\Order\OrderGatewayInterface;
use Modules\Payment\Exceptions\PaymentException;
use Modules\Payment\Models\Payment;
use Modules\Payment\Schemas\Payment\PaymentSchema;
use Modules\Payment\Schemas\Payment\PaymentStatusEnum;

class UpdatePaymentAction
{
    public function __construct(protected Payment $model) {}

    /**
     * Manually confirm or fail a pending payment (e.g. the `manual` method).
     * Confirming also drives the order to `paid` atomically.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws PaymentException
     */
    public function handle(string $id, array $data): Payment
    {
        $payment = $this->model->query()->findOrFail($id);

        if ($payment->{PaymentSchema::STATUS} !== PaymentStatusEnum::PENDING) {
            throw PaymentException::invalidStatusTransition();
        }

        $status = PaymentStatusEnum::from($data[PaymentSchema::STATUS]);

        if ($status === PaymentStatusEnum::SUCCESS) {
            DB::transaction(function () use ($payment, $status): void {
                $payment->{PaymentSchema::STATUS} = $status;
                $payment->save();

                $markedAsPaid = app(OrderGatewayInterface::class)
                    ->markAsPaid($payment->{PaymentSchema::ORDER_ID});
                if (! $markedAsPaid) {
                    throw PaymentException::processInvalidOrder();
                }
            });

            event(PaymentSucceededEvent::fill([
                PaymentSchema::ID => $payment->{PaymentSchema::ID},
                PaymentSchema::ORDER_ID => $payment->{PaymentSchema::ORDER_ID},
                PaymentSchema::AMOUNT => $payment->{PaymentSchema::AMOUNT},
                PaymentSchema::STATUS => $status->value,
            ]));

            return $payment->refresh();
        }

        $payment->{PaymentSchema::STATUS} = $status;
        $payment->save();

        event(PaymentFailedEvent::fill([
            PaymentSchema::ID => $payment->{PaymentSchema::ID},
            PaymentSchema::ORDER_ID => $payment->{PaymentSchema::ORDER_ID},
            PaymentSchema::AMOUNT => $payment->{PaymentSchema::AMOUNT},
            PaymentSchema::STATUS => $status->value,
        ]));

        return $payment->refresh();
    }
}
