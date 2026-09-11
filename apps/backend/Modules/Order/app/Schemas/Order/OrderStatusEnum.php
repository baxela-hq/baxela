<?php

namespace Modules\Order\Schemas\Order;

enum OrderStatusEnum: string
{
    case PENDING = 'pending';

    case PROCESSING = 'processing';

    case SHIPPED = 'shipped';

    case COMPLETED = 'completed';

    case CANCELLED = 'cancelled';

    /**
     * @return array<int, self>
     */
    public function transitions(): array
    {
        return match ($this) {
            self::PENDING => [self::PROCESSING, self::SHIPPED, self::CANCELLED],
            self::PROCESSING => [self::SHIPPED, self::CANCELLED],
            self::SHIPPED => [self::COMPLETED],
            self::COMPLETED, self::CANCELLED => [],
        };
    }

    public function canTransitionTo(self $status): bool
    {
        return $this === $status || in_array($status, $this->transitions(), true);
    }
}
