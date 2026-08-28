<?php

namespace Modules\Inventory\Actions\Admin\InventoryStock;

use Modules\Inventory\Models\InventoryStock;

abstract class AbstractInventoryStockAction
{
    public function __construct(protected InventoryStock $model) {}
}
