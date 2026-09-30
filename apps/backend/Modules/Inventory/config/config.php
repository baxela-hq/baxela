<?php

return [
    'name' => 'Inventory',

    /**
     * Quantities at or below this threshold are considered low stock.
     * Drives the admin `filter[low_stock]` list filter; notifications
     * for depleted stock fire independently at zero.
     */
    'low_stock_threshold' => 5,
];
