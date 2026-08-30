<?php

namespace Modules\Core\Contracts\Events\Order;

use Modules\Core\Contracts\Events\AbstractBaseEvent;

class OrderCompletedEvent extends AbstractBaseEvent
{
    public int $id;

    public int $user_id;

    public string $status;
}
