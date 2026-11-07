<?php

namespace Modules\Order\Schemas\Order;

enum OrderPaymentStatusEnum: string
{
    case UNPAID = 'unpaid';

    case PAID = 'paid';

    case PARTIALLY_REFUNDED = 'partially_refunded';

    case REFUNDED = 'refunded';

    /**
     * @return array<int, self>
     */
    public function transitions(): array
    {
        return match ($this) {
            self::UNPAID => [self::PAID],
            self::PAID => [self::PARTIALLY_REFUNDED, self::REFUNDED],
            // The downward legs exist for gateway-initiated refund
            // reversals (OrderGateway::reverseRefund): money returned to
            // the merchant must restore the pre-refund status. applyRefund
            // keeps its forward-only flow.
            self::PARTIALLY_REFUNDED => [self::REFUNDED, self::PAID],
            self::REFUNDED => [self::PARTIALLY_REFUNDED, self::PAID],
        };
    }

    public function canTransitionTo(self $status): bool
    {
        return $this === $status || in_array($status, $this->transitions(), true);
    }
}
