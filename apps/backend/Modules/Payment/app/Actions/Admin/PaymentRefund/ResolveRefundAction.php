<?php

namespace Modules\Payment\Actions\Admin\PaymentRefund;

use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\Events\Payment\PaymentRefundedEvent;
use Modules\Core\Utils\Locale;
use Modules\Payment\Exceptions\PaymentException;
use Modules\Payment\Models\PaymentRefund;
use Modules\Payment\Schemas\Payment\PaymentSchema;
use Modules\Payment\Schemas\PaymentRefund\PaymentRefundSchema;
use Modules\Payment\Schemas\PaymentRefund\PaymentRefundStatusEnum;
use Modules\Payment\Support\FinalizesRefund;
use Modules\Payment\Support\FiresRefundFinalizedEvent;

class ResolveRefundAction
{
    /**
     * Close an unresolved ledger row: the gateway outcome was ambiguous
     * (timeout on reconciliation_required) or is still pending at the
     * gateway (a stale processing row — reservations are never
     * age-released, so an operator verifies the provider's actual outcome
     * before resolving). Confirming success runs the same order-accounting
     * finalization as the live flow; confirming failure frees the
     * reserved amount. Late webhooks cannot contradict a resolution:
     * terminal rows transition to nothing, and a contradicting delivery
     * is reported for deliberate reconciliation.
     *
     * @param  array{status: string, transaction_id?: string|null}  $data
     *
     * @throws PaymentException
     */
    public function handle(string $id, array $data): PaymentRefund
    {
        $status = PaymentRefundStatusEnum::from($data[PaymentRefundSchema::STATUS]);

        $refund = DB::transaction(function () use ($id, $status): PaymentRefund {
            $refund = PaymentRefund::query()->whereKey($id)->lockForUpdate()->firstOrFail();

            if (! $refund->{PaymentRefundSchema::STATUS}->canTransitionTo($status)) {
                throw PaymentException::refundInvalidStatus();
            }

            return $refund;
        });

        if ($status === PaymentRefundStatusEnum::SUCCESS) {
            [$full] = FinalizesRefund::success($refund, $data[PaymentRefundSchema::TRANSACTION_ID] ?? null);

            event(PaymentRefundedEvent::fill([
                // Explicit keys: PaymentRefundSchema::ID and PaymentSchema::ID
                // are both 'id' and would collide in the fill map.
                'id' => (int) $refund->{PaymentRefundSchema::ID},
                'payment_id' => (int) $refund->{PaymentRefundSchema::PAYMENT_ID},
                'order_id' => (int) $refund->{PaymentRefundSchema::ORDER_ID},
                PaymentSchema::AMOUNT => (float) $refund->{PaymentRefundSchema::AMOUNT},
                'full' => $full,
                PaymentRefundSchema::TRANSACTION_ID => (string) $refund->refresh()->{PaymentRefundSchema::TRANSACTION_ID},
                PaymentRefundSchema::STATUS => PaymentRefundStatusEnum::SUCCESS->value,
                'locale' => Locale::fromRequest(),
            ]));

            // Per-refund accounting signal (gap-B1 D10): fires after
            // commit through the shared dispatcher.
            FiresRefundFinalizedEvent::from($refund->refresh());

            return $refund->refresh();
        }

        $refund->update([PaymentRefundSchema::STATUS => PaymentRefundStatusEnum::FAILED]);

        return $refund->refresh();
    }
}
