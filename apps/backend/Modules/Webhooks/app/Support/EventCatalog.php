<?php

namespace Modules\Webhooks\Support;

use Modules\Core\Contracts\Events\Order\OrderCancelledEvent;
use Modules\Core\Contracts\Events\Order\OrderCreatedEvent;
use Modules\Core\Contracts\Events\Order\OrderPaidEvent;
use Modules\Core\Contracts\Events\Order\OrderReturnRequestedEvent;
use Modules\Core\Contracts\Events\Order\OrderShippedEvent;
use Modules\Core\Contracts\Events\Payment\PaymentRefundedEvent;
use Modules\Core\Contracts\Events\Shipping\ShipmentShippedEvent;

/**
 * The v1 webhook event catalog (gap-C2 D3): existing events only, mapped
 * to their public type strings. New events join by adding one entry.
 * source_key rules are per business transition (never the aggregate) so
 * legitimate later transitions are never suppressed:
 * order.paid per order (the PAID transition fires once),
 * shipment.shipped / refund.processed / return.requested per transition row.
 */
final class EventCatalog
{
    public const string API_VERSION = 'v1';

    public const string TEST_TYPE = 'test';

    /** @return array<string, class-string|null> type => event class (null = synthetic) */
    public static function types(): array
    {
        return [
            'order.created' => OrderCreatedEvent::class,
            'order.paid' => OrderPaidEvent::class,
            'order.cancelled' => OrderCancelledEvent::class,
            'order.shipped' => OrderShippedEvent::class,
            'shipment.shipped' => ShipmentShippedEvent::class,
            'refund.processed' => PaymentRefundedEvent::class,
            'return.requested' => OrderReturnRequestedEvent::class,
            self::TEST_TYPE => null,
        ];
    }

    public static function isValidType(string $type): bool
    {
        return array_key_exists($type, self::types());
    }
}
