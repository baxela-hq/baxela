<?php

namespace Modules\Order\Listeners\OrderPaid;

use Modules\Core\Contracts\Events\Order\OrderPaidEvent;
use Modules\Order\Actions\Admin\Invoice\IssueInvoiceAction;
use Modules\Order\Exceptions\OrderException;
use Throwable;

/**
 * Auto-issue the order's invoice when payment lands (gap-B1 Phase 3,
 * D9): gated by the invoices.auto_issue setting (default on) and
 * idempotent through IssueInvoiceAction's single-ACTIVE assertion —
 * listener retries and double delivery can never allocate a second
 * number. Every paid path funnels through OrderGateway::markAsPaid,
 * which is the single dispatch site of OrderPaidEvent, so webhook
 * settles, manual confirms and zero-total orders are all covered. A
 * failure here must never break the paid pipeline.
 */
class IssueInvoiceOnOrderPaid
{
    public function handle(OrderPaidEvent $event): void
    {
        if (! config('order.invoices.auto_issue', true)) {
            return;
        }

        try {
            app(IssueInvoiceAction::class)->handle($event->id);
        } catch (OrderException) {
            // Ineligible (e.g. a currency that vanished) — the admin can
            // still issue manually once the cause is fixed; not worth
            // failing the paid flow over.
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
