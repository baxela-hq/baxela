<?php

use Modules\Order\Transfer\OrderTransfer;

return [
    'name' => 'Order',

    // Financial documents (gap-B1 D9): invoices issue automatically when
    // an order is paid. The escape hatch for corrections is the admin's
    // manual issue/void/reissue surface, which works either way.
    'invoices' => [
        'auto_issue' => env('INVOICES_AUTO_ISSUE', true),
    ],

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
