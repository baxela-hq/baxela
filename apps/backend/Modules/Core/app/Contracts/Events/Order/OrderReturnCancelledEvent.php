<?php

namespace Modules\Core\Contracts\Events\Order;

use Modules\Core\Contracts\Events\AbstractBaseEvent;

class OrderReturnCancelledEvent extends AbstractBaseEvent
{
    public int $id;

    public int $order_id;

    public string $order_code;

    public int $user_id;

    public string $status;

    public string $locale;
}
