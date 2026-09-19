<?php

namespace Modules\Core\Contracts\Events\Order;

use Modules\Core\Contracts\Events\AbstractBaseEvent;

class OrderCreatedEvent extends AbstractBaseEvent
{
    public int $id;

    public int $user_id;

    public string $order_code;

    public string $status;

    public float $total_amount;

    public string $expires_at;

    public string $created_at;

    /**
     * Visitor language at checkout, kept so queued notifications
     * reach the customer in their own language.
     */
    public ?string $locale = null;
}
