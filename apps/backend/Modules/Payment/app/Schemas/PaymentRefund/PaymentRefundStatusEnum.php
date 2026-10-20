<?php

namespace Modules\Payment\Schemas\PaymentRefund;

enum PaymentRefundStatusEnum: string
{
    /**
     * Durable intent: the row exists and reserves its amount before any
     * gateway call is made.
     */
    case PENDING = 'pending';

    case SUCCESS = 'success';

    case FAILED = 'failed';

    /**
     * The gateway outcome is unknown (timeout / lost response). The amount
     * stays reserved until an admin resolves it — an uncertain refund is
     * never treated as definitively unsuccessful, and never double-freed.
     */
    case RECONCILIATION_REQUIRED = 'reconciliation_required';

    /**
     * @return array<int, self>
     */
    public function transitions(): array
    {
        return match ($this) {
            self::PENDING => [self::SUCCESS, self::FAILED, self::RECONCILIATION_REQUIRED],
            self::RECONCILIATION_REQUIRED => [self::SUCCESS, self::FAILED],
            self::SUCCESS, self::FAILED => [],
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
        return [self::PENDING, self::SUCCESS, self::RECONCILIATION_REQUIRED];
    }
}
