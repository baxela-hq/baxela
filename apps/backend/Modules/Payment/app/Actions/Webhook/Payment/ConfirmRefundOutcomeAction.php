<?php

namespace Modules\Payment\Actions\Webhook\Payment;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Core\Contracts\Events\Payment\PaymentRefundedEvent;
use Modules\Core\Contracts\Gateways\Core\CoreGatewayInterface;
use Modules\Core\Contracts\Gateways\Payment\DTOs\RefundWebhookOutcome;
use Modules\Core\Contracts\Gateways\Payment\DTOs\WebhookResult;
use Modules\Core\Utils\Locale;
use Modules\Payment\Actions\Admin\PaymentRefund\ReverseRefundAction;
use Modules\Payment\Models\Payment;
use Modules\Payment\Models\PaymentRefund;
use Modules\Payment\Schemas\Payment\PaymentSchema;
use Modules\Payment\Schemas\PaymentRefund\PaymentRefundSchema;
use Modules\Payment\Schemas\PaymentRefund\PaymentRefundStatusEnum;
use Modules\Payment\Support\FinalizesRefund;
use Modules\Payment\Support\FiresRefundFinalizedEvent;
use RuntimeException;

class ConfirmRefundOutcomeAction
{
    public function __construct(protected ReverseRefundAction $reverseRefund) {}

    /**
     * Route a verified refund-lifecycle webhook outcome into the ledger.
     * Correlation and validation come before any transition:
     *
     * 1. The echoed ledger uuid (Baxela's merchant reference, committed
     *    before the gateway call) resolves the row — the gateway refund
     *    id is the documented fallback for lifecycle events that don't
     *    echo it (verified per event against sandbox payloads).
     * 2. When the event carries the gateway payment id it must match the
     *    payment's stored one; amount and currency must match the ledger
     *    row when carried. Any mismatch is reported at error level with
     *    the identifiers and the delivery is acked — never finalized.
     * 3. Unknown references (refunds issued from the gateway dashboard)
     *    are acked at notice level — they were never reserved, so there
     *    is nothing to free and nothing to account.
     */
    public function handle(WebhookResult $result): void
    {
        $outcome = $result->refund_outcome;

        if ($outcome !== null) {
            $this->handleOutcome($outcome);
        }
    }

    /**
     * Route a verified refund-lifecycle webhook outcome into the ledger.
     * Correlation and validation come before any transition:
     *
     * 1. The echoed ledger uuid (Baxela's merchant reference, committed
     *    before the gateway call) resolves the row — the gateway refund
     *    id is the documented fallback for lifecycle events that don't
     *    echo it (verified per event against sandbox payloads).
     * 2. When the event carries the gateway payment id it must match the
     *    payment's stored one; amount and currency must match the ledger
     *    row when carried. Any mismatch is reported at error level with
     *    the identifiers and the delivery is acked — never finalized.
     * 3. Unknown references (refunds issued from the gateway dashboard)
     *    are acked at notice level — they were never reserved, so there
     *    is nothing to free and nothing to account.
     */
    public function handleOutcome(RefundWebhookOutcome $outcome): void
    {
        $refund = null;

        if ($outcome->reference !== null && $outcome->reference !== '') {
            $refund = PaymentRefund::query()
                ->where(PaymentRefundSchema::UUID, $outcome->reference)
                ->first();
        }

        if (is_null($refund) && $outcome->gateway_refund_id !== null) {
            // Documented fallback: a lifecycle event without the echoed
            // reference correlates through its own refund id.
            $refund = PaymentRefund::query()
                ->where(PaymentRefundSchema::TRANSACTION_ID, $outcome->gateway_refund_id)
                ->first();
        }

        if (is_null($refund)) {
            Log::notice('payment.refund webhook for an unknown refund — not issued through Baxela', [
                'reference' => $outcome->reference,
                'gateway_refund_id' => $outcome->gateway_refund_id,
                'status' => $outcome->status,
            ]);

            return;
        }

        $payment = Payment::query()->find($refund->{PaymentRefundSchema::PAYMENT_ID});

        if (is_null($payment)) {
            report(new RuntimeException("refund {$refund->{PaymentRefundSchema::UUID}} references a missing payment"));

            return;
        }

        if ($outcome->gateway_payment_id !== null
            && (string) $payment->{PaymentSchema::GATEWAY_TRANSACTION_ID} !== $outcome->gateway_payment_id) {
            report(new RuntimeException(
                "refund webhook payment mismatch: refund {$refund->{PaymentRefundSchema::UUID}}"
                ." belongs to payment {$payment->{PaymentSchema::GATEWAY_TRANSACTION_ID}},"
                ." event announced {$outcome->gateway_payment_id}",
            ));

            return;
        }

        if ($outcome->amount_minor !== null
            && FinalizesRefund::minor($refund->{PaymentRefundSchema::AMOUNT}) !== $outcome->amount_minor) {
            report(new RuntimeException(
                "refund webhook amount mismatch: refund {$refund->{PaymentRefundSchema::UUID}}"
                ." expected {$outcome->amount_minor}, ledger holds {$refund->{PaymentRefundSchema::AMOUNT}}",
            ));

            return;
        }

        if ($outcome->currency !== null) {
            $currency = app(CoreGatewayInterface::class)
                ->getCurrencyById((int) $payment->{PaymentSchema::CURRENCY_ID});

            if ($currency !== null && strcasecmp($outcome->currency, (string) $currency->code) !== 0) {
                report(new RuntimeException(
                    "refund webhook currency mismatch: refund {$refund->{PaymentRefundSchema::UUID}}"
                    ." event announced {$outcome->currency}, payment holds {$currency->code}",
                ));

                return;
            }
        }

        $this->apply($refund, $outcome);
    }

    private function apply(PaymentRefund $refund, RefundWebhookOutcome $outcome): void
    {
        match ($outcome->status) {
            'success' => $this->confirmSuccess($refund, $outcome),
            'failed' => $this->confirmFailure($refund, $outcome),
            'reversed' => $this->reverseRefund->handle($refund),
            default => report(new RuntimeException(
                "refund webhook carried an unknown outcome status '{$outcome->status}'"
                ." for refund {$refund->{PaymentRefundSchema::UUID}}",
            )),
        };
    }

    private function confirmSuccess(PaymentRefund $refund, RefundWebhookOutcome $outcome): void
    {
        $current = $this->lockedStatus($refund);

        // Late/duplicate delivery: the row already finalized — nothing to
        // confirm, and a regressive transition must never run.
        if ($current === PaymentRefundStatusEnum::SUCCESS
            || ! $current->canTransitionTo(PaymentRefundStatusEnum::SUCCESS)) {
            return;
        }

        // Keep an id persisted at accept time when the event carries none.
        $gatewayId = $outcome->gateway_refund_id ?? $refund->{PaymentRefundSchema::TRANSACTION_ID};

        [$full] = FinalizesRefund::success($refund, $gatewayId);

        event(PaymentRefundedEvent::fill([
            'id' => (int) $refund->{PaymentRefundSchema::ID},
            'payment_id' => (int) $refund->{PaymentRefundSchema::PAYMENT_ID},
            'order_id' => (int) $refund->{PaymentRefundSchema::ORDER_ID},
            'amount' => (float) $refund->{PaymentRefundSchema::AMOUNT},
            'full' => $full,
            'transaction_id' => (string) $refund->refresh()->{PaymentRefundSchema::TRANSACTION_ID},
            'status' => PaymentRefundStatusEnum::SUCCESS->value,
            'locale' => Locale::fromRequest(),
        ]));

        // Per-refund accounting signal (gap-B1 D10): fires after commit
        // through the shared dispatcher.
        FiresRefundFinalizedEvent::from($refund->refresh());
    }

    private function confirmFailure(PaymentRefund $refund, RefundWebhookOutcome $outcome): void
    {
        $current = $this->lockedStatus($refund);

        if ($current === PaymentRefundStatusEnum::SUCCESS) {
            // The accounting had already been applied for this refund and
            // the gateway has now definitively walked it back — reverse
            // the refund (and its accounting) instead of silently keeping
            // money recorded as refunded.
            $this->reverseRefund->handle($refund);

            return;
        }

        if (! $current->canTransitionTo(PaymentRefundStatusEnum::FAILED)) {
            return; // REVERSED / FAILED: terminal, nothing to fail
        }

        $refund->update(array_filter([
            PaymentRefundSchema::STATUS => PaymentRefundStatusEnum::FAILED,
            PaymentRefundSchema::TRANSACTION_ID => $refund->{PaymentRefundSchema::TRANSACTION_ID}
                ?? $outcome->gateway_refund_id,
        ]));
    }

    /**
     * Re-read the row under a lock so the decision reflects concurrent
     * deliveries, not a stale snapshot.
     */
    private function lockedStatus(PaymentRefund $refund): PaymentRefundStatusEnum
    {
        return DB::transaction(function () use ($refund): PaymentRefundStatusEnum {
            $fresh = PaymentRefund::query()
                ->whereKey($refund->{PaymentRefundSchema::ID})
                ->lockForUpdate()
                ->first();

            $refund->setRawAttributes($fresh->getAttributes());

            return $fresh->{PaymentRefundSchema::STATUS};
        });
    }
}
