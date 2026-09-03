<?php

namespace Modules\Core\Contracts\Events\Payment;

use Modules\Core\Contracts\Events\AbstractBaseEvent;

class PaymentInitiatedEvent extends AbstractBaseEvent
{
    public int $id;

    public int $order_id;

    public float $amount;

    public string $method;

    public string $status;
}
