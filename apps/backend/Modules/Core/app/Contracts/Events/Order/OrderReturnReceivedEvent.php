<?php

namespace Modules\Core\Contracts\Events\Order;

use Modules\Core\Contracts\Events\AbstractBaseEvent;

class OrderReturnReceivedEvent extends AbstractBaseEvent
{
    public int $id;

    public int $order_id;

    public string $order_code;

    public int $user_id;

    public string $status;

    public bool $stock_restored;

    public string $locale;
}
