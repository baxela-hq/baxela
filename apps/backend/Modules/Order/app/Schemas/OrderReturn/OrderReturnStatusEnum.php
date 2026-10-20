<?php

namespace Modules\Order\Schemas\OrderReturn;

enum OrderReturnStatusEnum: string
{
    case REQUESTED = 'requested';

    case APPROVED = 'approved';

    case REJECTED = 'rejected';

    case SHIPPED = 'shipped';

    case RECEIVED = 'received';

    case COMPLETED = 'completed';

    case CANCELLED = 'cancelled';

    /**
     * @return array<int, self>
     */
    public function transitions(): array
    {
        return match ($this) {
            self::REQUESTED => [self::APPROVED, self::REJECTED, self::CANCELLED],
            self::APPROVED => [self::SHIPPED, self::CANCELLED],
            self::SHIPPED => [self::RECEIVED],
            self::RECEIVED => [self::COMPLETED],
            self::REJECTED, self::COMPLETED, self::CANCELLED => [],
        };
    }

    public function canTransitionTo(self $status): bool
    {
        return $this === $status || in_array($status, $this->transitions(), true);
    }

    /**
     * A return occupying the order's one-open-return slot.
     */
    public static function open(): array
    {
        return [self::REQUESTED, self::APPROVED, self::SHIPPED];
    }
}
