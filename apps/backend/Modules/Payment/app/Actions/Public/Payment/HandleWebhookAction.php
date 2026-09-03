<?php

namespace Modules\Payment\Actions\Public\Payment;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\Events\Payment\PaymentFailedEvent;
use Modules\Core\Contracts\Events\Payment\PaymentSucceededEvent;
use Modules\Core\Contracts\Gateways\Order\OrderGatewayInterface;
use Modules\Payment\Exceptions\PaymentException;
use Modules\Payment\Gateways\PaymentDriverManager;
use Modules\Payment\Models\Payment;
use Modules\Payment\Schemas\Payment\PaymentSchema;
use Modules\Payment\Schemas\Payment\PaymentStatusEnum;

class HandleWebhookAction
{
    public function __construct(protected Payment $model) {}

    /**
     * @throws PaymentException
     */
    public function handle(string $driver, Request $request): void
    {
        $result = app(PaymentDriverManager::class)
            ->forName($driver)
            ->handleWebhook($request);

        $payment = $this->model->query()
            ->where(PaymentSchema::TRANSACTION_ID, $result->transaction_id)
            ->first();

        if (is_null($payment)) {
            throw PaymentException::webhookInvalid();
        }

        // Idempotent: gateway retries for a settled payment are acked as no-ops
        if ($payment->{PaymentSchema::STATUS} !== PaymentStatusEnum::PENDING) {
            return;
        }

        $status = $result->status === PaymentStatusEnum::SUCCESS->value
            ? PaymentStatusEnum::SUCCESS
            : PaymentStatusEnum::FAILED;

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

            event(PaymentSucceededEvent::fill($this->eventPayload($payment, $status)));

            return;
        }

        $payment->{PaymentSchema::STATUS} = $status;
        $payment->save();

        event(PaymentFailedEvent::fill($this->eventPayload($payment, $status)));
    }

    /**
     * @return array<string, mixed>
     */
    private function eventPayload(Payment $payment, PaymentStatusEnum $status): array
    {
        return [
            PaymentSchema::ID => $payment->{PaymentSchema::ID},
            PaymentSchema::ORDER_ID => $payment->{PaymentSchema::ORDER_ID},
            PaymentSchema::AMOUNT => $payment->{PaymentSchema::AMOUNT},
            PaymentSchema::STATUS => $status->value,
        ];
    }
}
