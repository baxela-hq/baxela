<?php

namespace Modules\Core\Contracts\Events\Order;

use Modules\Core\Contracts\Events\AbstractBaseEvent;

class OrderRefundedEvent extends AbstractBaseEvent
{
    public int $id;

    public int $user_id;

    public string $order_code;

    public string $payment_status;

    public float $total_amount;

    public ?string $locale = null;
}
