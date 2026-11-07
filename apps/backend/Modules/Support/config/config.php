<?php

use Modules\Support\Transfer\SupportTransfer;

return [
    'name' => 'Support',

    // Per-user ticket writes per minute on the customer endpoints.
    'rate_limit' => [
        'create' => env('SUPPORT_TICKET_CREATE_RATE_LIMIT', 10),

        'reply' => env('SUPPORT_TICKET_REPLY_RATE_LIMIT', 30),
    ],

    // Data-transfer standard registration — picked up by the Transfer
    // module's registry (see docs/transfer.md).
    'transfer' => [
        'class' => SupportTransfer::class,
    ],
];
