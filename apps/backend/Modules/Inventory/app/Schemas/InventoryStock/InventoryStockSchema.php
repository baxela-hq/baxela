<?php

namespace Modules\Inventory\Schemas\InventoryStock;

use Modules\Core\Schemas\Shared\PkAndTimestampsTrait;
use Modules\Inventory\Schemas\Module;

class InventoryStockSchema
{
    use PkAndTimestampsTrait;

    public const string TABLE = Module::DB_PREFIX.'inventory_stocks';

    public const string VARIANT_ID = 'variant_id';

    public const string QUANTITY = 'quantity';
}
