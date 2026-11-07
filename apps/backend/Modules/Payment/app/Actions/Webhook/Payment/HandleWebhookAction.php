<?php

namespace Modules\Payment\Actions\Webhook\Payment;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\Events\Payment\PaymentFailedEvent;
use Modules\Core\Contracts\Events\Payment\PaymentSucceededEvent;
use Modules\Core\Contracts\Gateways\Order\OrderGatewayInterface;
use Modules\Core\Utils\Locale;
use Modules\Payment\Exceptions\PaymentException;
use Modules\Payment\Gateways\PaymentDriverManager;
use Modules\Payment\Models\Payment;
use Modules\Payment\Schemas\Payment\PaymentSchema;
use Modules\Payment\Schemas\Payment\PaymentStatusEnum;
use RuntimeException;

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

        // A refund-lifecycle outcome routes to the refund ledger, never to
        // payment settlement — the two branches never touch each other's
        // rows. Gateways whose notifications announce several refund
        // entries at once (Mercado Pago) deliver a list; the ledger's
        // transition guards make re-announced entries no-ops.
        if ($result->refund_outcomes !== null) {
            foreach ($result->refund_outcomes as $outcome) {
                app(ConfirmRefundOutcomeAction::class)->handleOutcome($outcome);
            }

            return;
        }

        if ($result->refund_outcome !== null) {
            app(ConfirmRefundOutcomeAction::class)->handle($result);

            return;
        }

        $payment = $this->model->query()
            ->where(PaymentSchema::TRANSACTION_ID, $result->transaction_id)
            ->first();

        if (is_null($payment)) {
            throw PaymentException::webhookInvalid();
        }

        // Persist the gateway-side payment identifier the refund APIs key
        // on — the authoritative id arrives with the settling webhook. A
        // conflicting value on a re-delivery is an integrity warning, not
        // a reason to reject the (already idempotent) settle.
        if ($result->gateway_transaction_id !== null) {
            $stored = $payment->{PaymentSchema::GATEWAY_TRANSACTION_ID};

            if ((string) $stored === '') {
                $payment->update([PaymentSchema::GATEWAY_TRANSACTION_ID => $result->gateway_transaction_id]);
            } elseif ((string) $stored !== $result->gateway_transaction_id) {
                report(new RuntimeException(
                    "payment {$payment->id} gateway transaction id mismatch:"
                    ." stored {$stored}, webhook announced {$result->gateway_transaction_id}",
                ));
            }
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
            'locale' => Locale::fromRequest(),
        ];
    }
}
