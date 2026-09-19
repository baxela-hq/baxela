<?php

namespace Modules\Core\Contracts\Events\Payment;

use Modules\Core\Contracts\Events\AbstractBaseEvent;

class PaymentFailedEvent extends AbstractBaseEvent
{
    public int $id;

    public int $order_id;

    public float $amount;

    public string $status;

    public ?string $locale = null;
}
