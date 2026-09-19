<?php

namespace Modules\Core\Contracts\Events\Order;

use Modules\Core\Contracts\Events\AbstractBaseEvent;

class OrderShippedEvent extends AbstractBaseEvent
{
    public int $id;

    public int $user_id;

    public string $order_code;

    public string $status;

    public ?string $locale = null;
}
