<?php

namespace Modules\Order\Schemas\Order;

enum OrderPaymentStatusEnum: string
{
    case UNPAID = 'unpaid';

    case PAID = 'paid';

    case REFUNDED = 'refunded';

    /**
     * @return array<int, self>
     */
    public function transitions(): array
    {
        return match ($this) {
            self::UNPAID => [self::PAID],
            self::PAID => [self::REFUNDED],
            self::REFUNDED => [],
        };
    }

    public function canTransitionTo(self $status): bool
    {
        return $this === $status || in_array($status, $this->transitions(), true);
    }
}
