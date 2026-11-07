<?php

namespace Modules\Payment\Actions\Admin\PaymentRefund;

use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\Events\Payment\PaymentRefundReversedEvent;
use Modules\Core\Contracts\Gateways\Order\OrderGatewayInterface;
use Modules\Core\Utils\Locale;
use Modules\Payment\Models\Payment;
use Modules\Payment\Models\PaymentRefund;
use Modules\Payment\Schemas\Payment\PaymentSchema;
use Modules\Payment\Schemas\Payment\PaymentStatusEnum;
use Modules\Payment\Schemas\PaymentRefund\PaymentRefundSchema;
use Modules\Payment\Schemas\PaymentRefund\PaymentRefundStatusEnum;
use Modules\Payment\Support\FinalizesRefund;

class ReverseRefundAction
{
    /**
     * The gateway reversed a refund it had previously issued (Adyen
     * REFUNDED_REVERSED, a late REFUND_FAILED on a finalized refund).
     * Two flavors, both final:
     *
     * - From SUCCESS the refund had already moved order accounting, so the
     *   row flips and the order's refunded total is RECOMPUTED from the
     *   ledger's success-only rows (never subtracted) inside one
     *   transaction — ledger and accounting commit together or not at
     *   all; the event fires only after the commit.
     * - From any pre-terminal state (a reversal webhook racing the accept
     *   response, or after a timeout) the refund never moved order
     *   accounting, so the flip alone releases the reservation.
     *
     * Idempotent: a duplicate delivery hits the transition guard and
     * no-ops; FAILED rows can never reverse (the gateway had rejected the
     * refund — nothing completed to walk back).
     *
     * @return PaymentRefund|null the reversed row, or null when nothing
     *                            was applied (duplicate/late delivery)
     */
    public function handle(PaymentRefund $refund): ?PaymentRefund
    {
        $restoredAccounting = false;

        $applied = DB::transaction(function () use ($refund, &$restoredAccounting): bool {
            $refund = PaymentRefund::query()
                ->whereKey($refund->{PaymentRefundSchema::ID})
                ->lockForUpdate()
                ->first();

            $current = $refund->{PaymentRefundSchema::STATUS};

            // A duplicate delivery is a no-op, and a rejected refund can
            // never be reversed — the gateway never completed anything.
            if ($current === PaymentRefundStatusEnum::REVERSED
                || ! $current->canTransitionTo(PaymentRefundStatusEnum::REVERSED)) {
                return false;
            }

            $restoredAccounting = $current === PaymentRefundStatusEnum::SUCCESS;

            $refund->update([
                PaymentRefundSchema::STATUS => PaymentRefundStatusEnum::REVERSED,
            ]);

            if ($restoredAccounting) {
                // The row is already REVERSED inside this transaction, so
                // the success-only sums exclude it — the order accounting
                // is recomputed from what actually remains refunded.
                $paidTotalMinor = FinalizesRefund::minor(Payment::query()
                    ->where(PaymentSchema::ORDER_ID, $refund->{PaymentRefundSchema::ORDER_ID})
                    ->where(PaymentSchema::STATUS, PaymentStatusEnum::SUCCESS)
                    ->sum(PaymentSchema::AMOUNT));

                $refundedTotalMinor = FinalizesRefund::minor(PaymentRefund::query()
                    ->where(PaymentRefundSchema::ORDER_ID, $refund->{PaymentRefundSchema::ORDER_ID})
                    ->where(PaymentRefundSchema::STATUS, PaymentRefundStatusEnum::SUCCESS)
                    ->sum(PaymentRefundSchema::AMOUNT));

                app(OrderGatewayInterface::class)->reverseRefund(
                    (int) $refund->{PaymentRefundSchema::ORDER_ID},
                    $refundedTotalMinor,
                    $paidTotalMinor,
                );
            }

            return true;
        });

        if (! $applied) {
            return null;
        }

        event(PaymentRefundReversedEvent::fill([
            // Explicit keys: PaymentRefundSchema::ID and PaymentSchema::ID
            // are both 'id' and would collide in the fill map.
            'id' => (int) $refund->{PaymentRefundSchema::ID},
            'payment_id' => (int) $refund->{PaymentRefundSchema::PAYMENT_ID},
            'order_id' => (int) $refund->{PaymentRefundSchema::ORDER_ID},
            'amount' => (float) $refund->{PaymentRefundSchema::AMOUNT},
            'transaction_id' => (string) $refund->{PaymentRefundSchema::TRANSACTION_ID},
            'restored_accounting' => $restoredAccounting,
            'locale' => Locale::fromRequest(),
        ]));

        return $refund->refresh();
    }
}
