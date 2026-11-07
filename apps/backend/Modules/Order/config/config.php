<?php

use Modules\Order\Transfer\OrderTransfer;

return [
    'name' => 'Order',

    'returns' => [
        // Days after payment (falling back to placement) within which a
        // customer may request a return for a shipped/completed order.
        'window_days' => 14,
    ],

    // Data-transfer standard registration — picked up by the Transfer
    // module's registry (see docs/transfer.md).
    'transfer' => [
        'class' => OrderTransfer::class,
    ],
];
