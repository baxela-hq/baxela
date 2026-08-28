<?php

namespace Modules\Order\Actions\Admin\Order;

use Modules\Order\Models\Order;

abstract class AbstractOrderAction
{
    public function __construct(protected Order $model) {}
}
