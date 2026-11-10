<?php

namespace Modules\Order\Listeners\PaymentRefundFinalized;

use Modules\Core\Contracts\Events\Payment\RefundFinalizedEvent;
use Modules\Order\Actions\Admin\CreditNote\IssueCreditNoteAction;
use Modules\Order\Exceptions\OrderException;
use Throwable;

/**
 * The refund-backed credit-note policy (gap-B1 Phase 4, D5): one credit
 * note per finalized refund, cut against the order's invoice. The event
 * fires at-least-once after the refund's finalize commit; the action's
 * unique refund_uuid makes duplicates no-ops. A policy failure (e.g.
 * an ineligible order after a hand-deleted invoice) is reported, never
 * rethrown — the refund itself is already finalized money movement.
 */
class IssueCreditNoteOnRefundFinalized
{
    public function handle(RefundFinalizedEvent $event): void
    {
        try {
            app(IssueCreditNoteAction::class)->handle(
                orderId: $event->order_id,
                refundUuid: $event->refund_uuid,
                amountMinor: $event->amount_minor,
                reason: $event->reason,
            );
        } catch (OrderException $exception) {
            // The refund stands; the accounting adjustment needs human
            // eyes. Reconcile surfaces it on its next run.
            report($exception);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
