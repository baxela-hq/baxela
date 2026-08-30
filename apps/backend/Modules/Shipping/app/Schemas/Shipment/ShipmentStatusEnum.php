<?php

namespace Modules\Shipping\Schemas\Shipment;

enum ShipmentStatusEnum: string
{
    case PENDING = 'pending';

    case PACKED = 'packed';

    case SHIPPED = 'shipped';

    case IN_TRANSIT = 'in_transit';

    case DELIVERED = 'delivered';

    case FAILED = 'failed';

    /**
     * @return array<int, self>
     */
    public function transitions(): array
    {
        return match ($this) {
            self::PENDING => [self::PACKED, self::FAILED],
            self::PACKED => [self::SHIPPED, self::FAILED],
            self::SHIPPED => [self::IN_TRANSIT, self::FAILED],
            self::IN_TRANSIT => [self::DELIVERED, self::FAILED],
            self::DELIVERED, self::FAILED => [],
        };
    }

    public function canTransitionTo(self $status): bool
    {
        return $this === $status || in_array($status, $this->transitions(), true);
    }
}
