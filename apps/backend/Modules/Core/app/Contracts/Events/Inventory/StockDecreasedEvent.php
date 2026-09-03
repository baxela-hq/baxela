<?php

namespace Modules\Core\Contracts\Events\Inventory;

use Modules\Core\Contracts\Events\AbstractBaseEvent;

class StockDecreasedEvent extends AbstractBaseEvent
{
    public int $variant_id;

    public int $quantity;
}
