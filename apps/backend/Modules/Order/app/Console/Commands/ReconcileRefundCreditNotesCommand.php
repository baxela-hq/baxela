<?php

namespace Modules\Order\Console\Commands;

use Illuminate\Console\Command;
use Modules\Core\Support\Money;
use Modules\Order\Actions\Admin\CreditNote\IssueCreditNoteAction;
use Modules\Order\Exceptions\OrderException;
use Modules\Order\Models\OrderCreditNote;
use Modules\Payment\Models\PaymentRefund;
use Modules\Payment\Schemas\PaymentRefund\PaymentRefundSchema;
use Modules\Payment\Schemas\PaymentRefund\PaymentRefundStatusEnum;
use Throwable;

/**
 * Closes the D15 crash window: a finalized refund whose refund_uuid has
 * no credit note gets one, through the same idempotent path the event
 * listener uses (duplicate events and reconcile runs are all no-ops on
 * the unique key). Scheduled daily; doubles as the ops tool when a
 * policy failure suppressed a listener.
 */
class ReconcileRefundCreditNotesCommand extends Command
{
    protected $signature = 'order:reconcile-refund-credit-notes';

    protected $description = 'Cut credit notes for finalized refunds whose event was lost or suppressed (D15)';

    public function handle(): int
    {
        $refundUuids = OrderCreditNote::query()
            ->whereNotNull('refund_uuid')
            ->pluck('refund_uuid')
            ->all();

        $orphans = PaymentRefund::query()
            ->where(PaymentRefundSchema::STATUS, PaymentRefundStatusEnum::SUCCESS)
            ->when($refundUuids !== [], fn ($query) => $query->whereNotIn(PaymentRefundSchema::UUID, $refundUuids))
            ->get();

        if ($orphans->isEmpty()) {
            $this->info('No unreconciled finalized refunds.');

            return self::SUCCESS;
        }

        $issued = 0;
        $failed = 0;

        foreach ($orphans as $refund) {
            try {
                app(IssueCreditNoteAction::class)->handle(
                    orderId: (int) $refund->{PaymentRefundSchema::ORDER_ID},
                    refundUuid: (string) $refund->{PaymentRefundSchema::UUID},
                    amountMinor: Money::fromDecimal((string) $refund->{PaymentRefundSchema::AMOUNT}),
                    reason: $refund->{PaymentRefundSchema::REASON},
                );
                $issued++;
            } catch (OrderException|Throwable $exception) {
                report($exception);
                $failed++;
            }
        }

        $this->info("Reconciled {$issued} refund(s)".($failed > 0 ? "; {$failed} FAILED (reported)" : '').'.');

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
