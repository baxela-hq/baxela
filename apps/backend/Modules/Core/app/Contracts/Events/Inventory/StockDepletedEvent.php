<?php

namespace Modules\Core\Contracts\Events\Inventory;

use Modules\Core\Contracts\Events\AbstractBaseEvent;

class StockDepletedEvent extends AbstractBaseEvent
{
    public int $variant_id;
}
