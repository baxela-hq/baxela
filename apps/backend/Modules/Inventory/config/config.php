<?php

use Modules\Inventory\Transfer\InventoryTransfer;

return [
    'name' => 'Inventory',

    /**
     * Quantities at or below this threshold are considered low stock.
     * Drives the admin `filter[low_stock]` list filter; notifications
     * for depleted stock fire independently at zero.
     */
    'low_stock_threshold' => 5,

    // Data-transfer standard registration — picked up by the Transfer
    // module's registry (see docs/transfer.md).
    'transfer' => [
        'class' => InventoryTransfer::class,
    ],
];
