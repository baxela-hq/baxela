<?php

use Modules\Notification\Transfer\NotificationTransfer;

return [
    'name' => 'Notification',

    // Data-transfer standard registration — picked up by the Transfer
    // module's registry (see docs/transfer.md).
    'transfer' => [
        'class' => NotificationTransfer::class,
    ],
];
