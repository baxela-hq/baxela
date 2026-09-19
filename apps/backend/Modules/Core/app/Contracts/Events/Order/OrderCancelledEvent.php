<?php

namespace Modules\Core\Contracts\Events\Order;

use Modules\Core\Contracts\Events\AbstractBaseEvent;

class OrderCancelledEvent extends AbstractBaseEvent
{
    public int $id;

    public int $user_id;

    public string $order_code;

    public string $status;

    /** Why the order was cancelled, e.g. "expired", "cancelled_by_customer". */
    public ?string $reason = null;

    public ?string $locale = null;
}
