<?php

namespace Modules\Core\Contracts\Events\Inventory;

use Modules\Core\Contracts\Events\AbstractBaseEvent;

class StockIncreasedEvent extends AbstractBaseEvent
{
    public int $variant_id;

    public int $quantity;
}
