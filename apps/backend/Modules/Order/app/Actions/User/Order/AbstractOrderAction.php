<?php

namespace Modules\Order\Actions\User\Order;

use Modules\Order\Models\Order;

class AbstractOrderAction
{
    public function __construct(protected Order $order) {}
}
