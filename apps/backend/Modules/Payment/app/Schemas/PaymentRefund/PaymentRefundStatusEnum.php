<?php

namespace Modules\Payment\Schemas\PaymentRefund;

enum PaymentRefundStatusEnum: string
{
    /**
     * Durable intent: the row exists and reserves its amount before any
     * gateway call is made.
     */
    case PENDING = 'pending';

    /**
     * The gateway accepted the refund but its definitive outcome only
     * arrives via webhook (Adyen, Checkout.com, Razorpay). The amount
     * stays reserved until the gateway confirms or rejects.
     */
    case PROCESSING = 'processing';

    case SUCCESS = 'success';

    case FAILED = 'failed';

    /**
     * The gateway outcome is unknown (timeout / lost response). The amount
     * stays reserved until an admin resolves it — an uncertain refund is
     * never treated as definitively unsuccessful, and never double-freed.
     */
    case RECONCILIATION_REQUIRED = 'reconciliation_required';

    /**
     * The gateway reversed a previously issued refund and the money
     * returned to the merchant account. Terminal: the refund's amount is
     * refundable again. Reversal is finalized in two flavors — from
     * SUCCESS it also restores the order accounting (see
     * Actions\Admin\PaymentRefund\ReverseRefundAction); from any
     * pre-terminal state the refund never moved order accounting, so
     * there is nothing to restore.
     */
    case REVERSED = 'reversed';

    /**
     * @return array<int, self>
     */
    public function transitions(): array
    {
        return match ($this) {
            // A verified gateway reversal may land while the accept
            // response is still in flight (webhook beats HTTP) or after it
            // timed out — it is definitive in every pre-terminal state.
            self::PENDING => [self::PROCESSING, self::SUCCESS, self::FAILED, self::RECONCILIATION_REQUIRED, self::REVERSED],
            self::PROCESSING => [self::SUCCESS, self::FAILED, self::REVERSED],
            self::RECONCILIATION_REQUIRED => [self::SUCCESS, self::FAILED, self::REVERSED],
            self::SUCCESS => [self::REVERSED],
            self::FAILED, self::REVERSED => [],
        };
    }

    public function canTransitionTo(self $status): bool
    {
        return $this === $status || in_array($status, $this->transitions(), true);
    }

    /**
     * Statuses that hold their amount against the refundable balance.
     */
    public static function reserving(): array
    {
        return [self::PENDING, self::PROCESSING, self::SUCCESS, self::RECONCILIATION_REQUIRED];
    }
}
